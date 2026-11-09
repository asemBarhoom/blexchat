<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Conversation\UpdateConversationRequest;
use App\Http\Requests\Api\Message\SendMessageRequest;
use App\Http\Resources\Api\V1\ConversationCollection;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageCollection;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Services\Channels\ChannelNotLive;
use App\Services\Channels\MessagingClient;
use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Conversations', description: 'DM conversations across connected channels')]
class ConversationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/conversations',
        summary: 'List DM conversations',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [
            new OA\Parameter(name: 'channel_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'resolved', 'all'])),
            new OA\Parameter(name: 'folder', in: 'query', required: false, description: 'favorites: starred conversations; labels: those with at least one label; reminders: those with a reminder, soonest first', schema: new OA\Schema(type: 'string', enum: ['favorites', 'labels', 'reminders'])),
            new OA\Parameter(name: 'label', in: 'query', required: false, description: 'Only conversations with this label', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'By the last message: newest (default) or oldest first', schema: new OA\Schema(type: 'string', enum: ['newest', 'oldest'])),
            new OA\Parameter(name: 'state', in: 'query', required: false, description: 'human: the Needs you folder; automation: an automation is answering; open: neither', schema: new OA\Schema(type: 'string', enum: ['open', 'automation', 'human'])),
            new OA\Parameter(name: 'assigned', in: 'query', required: false, description: 'me: assigned to the signed-in user; unassigned: to no one', schema: new OA\Schema(type: 'string', enum: ['me', 'unassigned'])),
            new OA\Parameter(name: 'unread', in: 'query', required: false, description: 'true: only conversations with unread messages', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Conversation list; `meta.total` counts every conversation the filters match (the folder’s number, "All chats 6")')]
    )]
    public function index(Request $request): ConversationCollection
    {
        $channelIds = $request->user()->account()->channels()->pluck('id');

        $direction = $request->query('sort') === 'oldest' ? 'asc' : 'desc';

        $query = Conversation::with(Conversation::LIST_RELATIONS)
            ->whereIn('channel_id', $channelIds);

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->boolean('unread')) {
            $query->where('unread_count', '>', 0);
        }

        match ($request->query('folder')) {
            'favorites' => $query->where('starred', true),
            'labels' => $query->whereJsonLength('labels', '>', 0),
            'reminders' => $query->whereNotNull('remind_at'),
            default => null,
        };

        if ($request->filled('label')) {
            $query->whereJsonContains('labels', (string) $request->query('label'));
        }

        match ($request->query('assigned')) {
            'me' => $query->where('assigned_to', $request->user()->id),
            'unassigned' => $query->whereNull('assigned_to'),
            default => null,
        };

        if (in_array($request->query('state'), Conversation::STATES, true)) {
            $query->where('state', $request->query('state'));
        }

        $total = (clone $query)->count();

        // Reminders come soonest first; every other folder by its last message.
        if ($request->query('folder') === 'reminders') {
            $query->orderBy('remind_at')->orderBy('id');
        } else {
            $query->orderBy('last_message_at', $direction)->orderBy('id', $direction);
        }

        return (new ConversationCollection($query->cursorPaginate($request->integer('limit', 20))))
            ->additional(['meta' => ['total' => $total]]);
    }

    #[OA\Patch(
        path: '/api/v1/conversations/{id}',
        summary: 'Mark a conversation done, reopen it, mark it read, star it or label it',
        description: '"Mark done" sends `status: resolved`, "Reopen" sends `status: open`, and opening the thread sends `read: true`, which sets `unread` to 0. A new message from the customer reopens a resolved conversation.',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'status', type: 'string', enum: ['open', 'resolved'], description: 'Required without read'),
            new OA\Property(property: 'read', type: 'boolean', description: 'true sets unread to 0'),
            new OA\Property(property: 'starred', type: 'boolean', description: 'In or out of Favorites'),
            new OA\Property(property: 'labels', type: 'array', maxItems: 10, items: new OA\Items(type: 'string', maxLength: 30), description: 'The whole list; [] removes every label'),
            new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, description: 'Assign to the owner or a teammate (ids from `GET /team`); null unassigns. Assigning needs a plan with team roles and assignment (`has_assignment`)'),
            new OA\Property(property: 'remind_at', type: 'string', format: 'date-time', nullable: true, description: 'A reminder (the Reminders folder), in the future; null clears it. Setting one needs a paid plan. When it comes, the owner is emailed and `remind_due` turns true'),
        ], example: ['status' => 'resolved'])),
        responses: [
            new OA\Response(response: 200, description: 'The conversation as the list shows it'),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another owner’s conversation; or `code: plan_required` when a reminder is set on Free (show Upgrade)'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $plan = $request->user()->account()->subscription?->effectivePlan();

        if ($request->filled('remind_at') && ($plan === null || $plan->isFree())) {
            return response()->json(['message' => __('inbox.reminders_plan'), 'code' => 'plan_required'], 403);
        }

        if ($request->filled('assigned_to') && ! $request->user()->account()->currentPlan()->has_assignment) {
            return response()->json(['message' => __('team.assignment_plan'), 'code' => 'plan_required'], 403);
        }

        if ($request->has('assigned_to')) {
            $conversation->update(['assigned_to' => $request->validated('assigned_to')]);
        }

        $conversation->update(array_filter([
            'status' => $request->validated('status'),
            'unread_count' => $request->boolean('read') ? 0 : null,
            'starred' => $request->validated('starred'),
            'labels' => $request->has('labels') ? $request->validated('labels') : null,
        ], fn (mixed $value): bool => $value !== null));

        if ($request->has('remind_at')) {
            // A new reminder is emailed again when it comes.
            $conversation->update(['remind_at' => $request->validated('remind_at'), 'reminded_at' => null]);
        }

        // Marked done: it no longer waits on anyone.
        if ($request->validated('status') === 'resolved') {
            $conversation->markHandledByOwner();
        }

        return response()->json(['data' => new ConversationResource($conversation->load(Conversation::LIST_RELATIONS))]);
    }

    #[OA\Get(
        path: '/api/v1/conversations/{id}/messages',
        summary: 'Get messages in a conversation',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Message thread'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function messages(Request $request, Conversation $conversation): MessageCollection
    {
        abort_unless(
            $request->user()->account()->channels()->where('id', $conversation->channel_id)->exists(),
            403
        );

        $messages = $conversation->messages()
            ->with('comment.post')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($request->integer('limit', 30))
            ->get();

        return new MessageCollection($messages);
    }

    #[OA\Post(
        path: '/api/v1/conversations/{id}/messages',
        summary: 'Send a message in a conversation',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'content'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['text', 'text_with_button']),
                    new OA\Property(property: 'content', type: 'string'),
                    new OA\Property(property: 'button_label', type: 'string'),
                    new OA\Property(property: 'button_url', type: 'string', format: 'url'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message sent'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 502, description: 'The platform refused the message (e.g. the 24-hour messaging window has closed), or replies on it are not available yet (TikTok)'),
        ]
    )]
    public function sendMessage(SendMessageRequest $request, Conversation $conversation, InstagramClient $instagram, MessagingClient $messaging): JsonResponse
    {
        abort_unless(
            $request->user()->account()->channels()->where('id', $conversation->channel_id)->exists(),
            403
        );

        $platformMessageId = null;

        if ($conversation->channel->platform === 'instagram') {
            try {
                $platformMessageId = $instagram->sendMessage(
                    $conversation->channel,
                    $conversation->contact_platform_id,
                    InstagramClient::message($request->content, $request->button_label, $request->button_url),
                );
            } catch (RequestException|ConnectionException $exception) {
                return response()->json([
                    'message' => 'Instagram did not deliver the message.',
                    'code' => $exception instanceof ConnectionException ? 'provider_unavailable' : 'provider_rejected',
                    'platform' => 'instagram',
                    'retryable' => $exception instanceof ConnectionException || ($exception instanceof RequestException && $exception->response->status() >= 500),
                    'error' => $exception instanceof RequestException ? InstagramClient::errorMessage($exception) : null,
                ], 502);
            }
        } else {
            try {
                $platformMessageId = $messaging->send(
                    $conversation->channel,
                    $conversation->contact_platform_id,
                    MessagingClient::text($request->content, $request->button_label, $request->button_url),
                );
            } catch (RequestException|ConnectionException|ChannelNotLive $exception) {
                return response()->json([
                    'message' => $exception instanceof ChannelNotLive ? $exception->getMessage() : ucfirst($conversation->channel->platform).' did not deliver the message.',
                    'code' => $exception instanceof ChannelNotLive ? 'channel_not_live' : ($exception instanceof ConnectionException ? 'provider_unavailable' : 'provider_rejected'),
                    'platform' => $conversation->channel->platform,
                    'retryable' => $exception instanceof ConnectionException || ($exception instanceof RequestException && $exception->response->status() >= 500),
                    'error' => $exception instanceof RequestException ? $exception->response->json('error.message') : null,
                ], 502);
            }
        }

        $message = $conversation->messages()->create([
            'platform_message_id' => $platformMessageId,
            'direction' => 'outbound',
            'type' => $request->type,
            'content' => $request->content,
            'button_label' => $request->button_label,
            'button_url' => $request->button_url,
            'automated' => false,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message' => $request->content,
            'last_message_at' => now(),
        ]);
        // The owner answered, so the conversation is out of Needs you.
        $conversation->markHandledByOwner();

        return response()->json(['data' => new MessageResource($message)], 201);
    }
}
