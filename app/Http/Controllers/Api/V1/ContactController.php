<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Contact\ListContactsRequest;
use App\Http\Requests\Api\Contact\StoreContactRequest;
use App\Http\Requests\Api\Contact\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactCollection;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Contacts', description: 'Everyone who commented on or messaged the owner’s channels, or was added by hand (W22, W23)')]
#[OA\Schema(schema: 'Contact', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 12),
    new OA\Property(property: 'name', type: 'string', example: 'Reem Khalid'),
    new OA\Property(property: 'handle', type: 'string', nullable: true, example: 'reem.k', description: 'Username, or the phone number for someone added by hand'),
    new OA\Property(property: 'channel', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
    new OA\Property(property: 'channel_id', type: 'integer', nullable: true, description: 'Null for someone added by hand or whose channel was disconnected'),
    new OA\Property(property: 'avatar', type: 'string', nullable: true),
    new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string'), example: ['Winter abaya', 'Asked price']),
    new OA\Property(property: 'source', type: 'string', nullable: true, example: 'Auto-DM links from comments', description: 'The automation that brought them in. When null, `added` tells "Added by you" from "Messaged you"'),
    new OA\Property(property: 'source_automation_id', type: 'integer', nullable: true),
    new OA\Property(property: 'added', type: 'boolean', description: 'Added by hand from Create New Contact, with their consent'),
    new OA\Property(property: 'gender', type: 'string', nullable: true, enum: ['female', 'male']),
    new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Jeddah'),
    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+966 55 123 4821'),
    new OA\Property(property: 'email', type: 'string', nullable: true),
    new OA\Property(property: 'language', type: 'string', nullable: true, example: 'ar'),
    new OA\Property(property: 'subscribed', type: 'boolean'),
    new OA\Property(property: 'conversation', type: 'object', nullable: true, description: 'Detail only: their latest conversation, for "Open in Inbox" and the message count', properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'resolved']),
        new OA\Property(property: 'messages_count', type: 'integer'),
    ]),
    new OA\Property(property: 'first_seen_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'last_active_at', type: 'string', format: 'date-time', nullable: true, description: 'Null for someone added by hand who has not written yet'),
])]
#[OA\Schema(schema: 'ContactsUsage', description: 'The sidebar meter: contacts saved against the current plan’s limit', properties: [
    new OA\Property(property: 'used', type: 'integer', example: 6),
    new OA\Property(property: 'limit', type: 'integer', example: 25),
    new OA\Property(property: 'plan', type: 'string', example: 'free', description: 'The tier in force: Free with no plan chosen, or once a trial ended'),
    new OA\Property(property: 'over_limit', type: 'boolean', description: 'More saved than the plan holds, e.g. after a trial ended. Nobody is removed'),
])]
class ContactController extends Controller
{
    #[OA\Get(
        path: '/api/v1/contacts',
        summary: 'List contacts',
        description: 'Most recently active first. The search matches the name, handle, city, a tag or the channel’s name, ignoring case.',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100), example: 'jeddah'),
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger'])),
            new OA\Parameter(name: 'tag', in: 'query', required: false, description: 'Exact tag, ignoring case', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'source', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['automation', 'messaged', 'added'])),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'recent', enum: ['recent', 'newest', 'name'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contacts', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Contact')),
                new OA\Property(property: 'meta', properties: [
                    new OA\Property(property: 'total', type: 'integer', description: 'Matching the search and filters'),
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'per_page', type: 'integer'),
                    new OA\Property(property: 'last_page', type: 'integer'),
                    new OA\Property(property: 'usage', ref: '#/components/schemas/ContactsUsage'),
                ]),
            ])),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(ListContactsRequest $request): ContactCollection
    {
        $contacts = $request->user()->contacts()
            ->with('sourceAutomation')
            ->when($request->filled('search'), fn ($query) => $query->search($request->input('search')))
            ->when($request->filled('channel'), fn ($query) => $query->where('platform', $request->input('channel')))
            ->when($request->filled('tag'), fn ($query) => $query->withTag($request->input('tag')))
            ->when($request->filled('source'), fn ($query) => $query->fromSource($request->input('source')))
            ->sortedBy($request->input('sort', 'recent'))
            ->paginate($request->integer('per_page', 50));

        return new ContactCollection($contacts);
    }

    #[OA\Get(
        path: '/api/v1/contacts/usage',
        summary: 'Contacts used against the plan’s limit',
        description: 'The sidebar meter ("6/25").',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        responses: [new OA\Response(response: 200, description: 'Usage', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/ContactsUsage'),
        ]))]
    )]
    public function usage(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->contactsUsage()]);
    }

    #[OA\Post(
        path: '/api/v1/contacts',
        summary: 'Create a contact by hand',
        description: 'Create New Contact (W23). Saved as a WhatsApp contact reached by phone. Refused once the plan’s contacts limit is reached.',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['first_name', 'dial_code', 'phone', 'consent'],
            properties: [
                new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Maha'),
                new OA\Property(property: 'last_name', type: 'string', maxLength: 100, nullable: true, example: 'Saad'),
                new OA\Property(property: 'dial_code', type: 'string', example: '+966'),
                new OA\Property(property: 'phone', type: 'string', example: '55 123 4567', description: 'At least 7 digits'),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'gender', type: 'string', enum: ['female', 'male'], nullable: true),
                new OA\Property(property: 'consent', type: 'boolean', example: true, description: 'Must be true: they agreed to receive messages'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Contact'),
            ])),
            new OA\Response(response: 403, description: 'Contacts limit reached', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'usage', ref: '#/components/schemas/ContactsUsage'),
            ])),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreContactRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->canAddContact()) {
            return response()->json([
                'message' => 'Your plan’s contacts limit is reached. Upgrade to add more.',
                'usage' => $user->contactsUsage(),
            ], 403);
        }

        $contact = $user->contacts()->create($request->contactAttributes());

        return response()->json(['data' => new ContactResource($contact)], 201);
    }

    #[OA\Get(
        path: '/api/v1/contacts/{id}',
        summary: 'Contact detail',
        description: 'W22. Includes their latest conversation for "Open in Inbox"; its messages come from GET /api/v1/conversations/{id}/messages.',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Contact', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Contact'),
            ])),
            new OA\Response(response: 404, description: 'Not one of the owner’s contacts'),
        ]
    )]
    public function show(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        $contact->load(['sourceAutomation', 'conversation' => fn (HasOne $query) => $query->withCount(['messages' => fn (Builder $messages) => $messages->where('direction', '!=', Message::DIRECTION_SYSTEM)])]);

        return response()->json(['data' => new ContactResource($contact)]);
    }

    #[OA\Patch(
        path: '/api/v1/contacts/{id}',
        summary: 'Update a contact',
        description: 'The contact page edits the tags; send the whole list. Send only the fields that change.',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'tags', type: 'array', maxItems: 30, items: new OA\Items(type: 'string', maxLength: 50), example: ['Winter abaya', 'VIP']),
            new OA\Property(property: 'name', type: 'string', maxLength: 200),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
            new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
            new OA\Property(property: 'city', type: 'string', nullable: true),
            new OA\Property(property: 'gender', type: 'string', enum: ['female', 'male'], nullable: true),
            new OA\Property(property: 'language', type: 'string', nullable: true),
            new OA\Property(property: 'subscribed', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Contact'),
            ])),
            new OA\Response(response: 404, description: 'Not one of the owner’s contacts'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        $contact->update($request->contactAttributes());

        return response()->json(['data' => new ContactResource($contact->load('sourceAutomation'))]);
    }

    #[OA\Delete(
        path: '/api/v1/contacts/{id}',
        summary: 'Delete a contact',
        description: 'Their conversation stays in the inbox. If they message again they come back as a new contact.',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not one of the owner’s contacts'),
        ]
    )]
    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        $contact->delete();

        return response()->json(['message' => 'Contact deleted.']);
    }
}
