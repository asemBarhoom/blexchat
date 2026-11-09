<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Automation\StoreAutomationRequest;
use App\Http\Requests\Api\Automation\StoreWizardAutomationRequest;
use App\Http\Requests\Api\Automation\ToggleAutomationAiRequest;
use App\Http\Requests\Api\Automation\ToggleAutomationRequest;
use App\Http\Requests\Api\Automation\UpdateAutomationRequest;
use App\Http\Requests\Api\Automation\UpdateWizardAutomationRequest;
use App\Http\Resources\Api\V1\AutomationCollection;
use App\Http\Resources\Api\V1\AutomationResource;
use App\Models\Automation;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Automations', description: 'Create and manage keyword-triggered automation rules')]
#[OA\Schema(schema: 'GoLiveRefusal', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', example: 'Couldn’t go live: your plan allows 3 live automations. Stop one, or upgrade.'),
    new OA\Property(property: 'code', type: 'string', enum: ['channel_disconnected', 'live_limit']),
    new OA\Property(property: 'channel', type: 'string', example: 'instagram', description: 'channel_disconnected only: the platform to reconnect'),
    new OA\Property(property: 'limit', type: 'integer', example: 3, description: 'live_limit only: how many may be live on the plan'),
])]
#[OA\Schema(schema: 'WizardAnswers', required: ['name', 'channel_id', 'kind', 'trigger', 'dm_text'], properties: [
    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Comment a word → send the catalogue', description: 'The automation’s name in the list: the template’s or the starter’s name'),
    new OA\Property(property: 'channel_id', type: 'integer', description: 'One of the owner’s channels. Fixed once saved: ignored on an edit'),
    new OA\Property(property: 'kind', type: 'string', enum: ['comment', 'story', 'dm'], description: 'Fixed once saved: ignored on an edit'),
    new OA\Property(property: 'template', type: 'string', nullable: true, example: 't1', description: 'New automations only: the template the wizard started from'),
    new OA\Property(property: 'post_mode', type: 'string', enum: ['specific', 'any', 'next'], description: 'Comment only, required there. any and next need a paid plan'),
    new OA\Property(property: 'post_id', type: 'integer', description: 'With post_mode specific: a post of the channel, from GET /api/v1/channels/{channel}/posts'),
    new OA\Property(property: 'trigger', type: 'string', enum: ['words', 'any']),
    new OA\Property(property: 'words', type: 'array', maxItems: 20, items: new OA\Items(type: 'string', maxLength: 100), example: ['السعر'], description: 'With trigger words: at least one'),
    new OA\Property(property: 'public_reply', type: 'boolean', description: 'Comment only: reply publicly under the comment'),
    new OA\Property(property: 'public_reply_text', type: 'string', maxLength: 300, example: 'أرسلنا لك التفاصيل بالخاص 💌', description: 'Required with public_reply'),
    new OA\Property(property: 'dm_text', type: 'string', maxLength: 640),
    new OA\Property(property: 'dm_button', type: 'string', maxLength: 20, nullable: true, description: 'Required with link_url; without a link no button is sent'),
    new OA\Property(property: 'link_url', type: 'string', format: 'uri', nullable: true, example: 'https://nourboutique.com/abaya-winter'),
    new OA\Property(property: 'ask_follow', type: 'boolean', description: 'Paid extra'),
    new OA\Property(property: 'follow_text', type: 'string', maxLength: 640, description: 'Required with ask_follow'),
    new OA\Property(property: 'follow_button', type: 'string', maxLength: 20, description: 'Required with ask_follow'),
    new OA\Property(property: 'ask_email', type: 'boolean', description: 'Paid extra'),
    new OA\Property(property: 'email_ask', type: 'string', maxLength: 640, description: 'Required with ask_email'),
    new OA\Property(property: 'follow_up', type: 'boolean', description: 'Paid extra'),
    new OA\Property(property: 'follow_up_after', type: 'string', enum: ['1h', '6h', '24h'], description: 'Required with follow_up'),
    new OA\Property(property: 'follow_up_text', type: 'string', maxLength: 640, description: 'Required with follow_up'),
])]
class AutomationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/automations',
        summary: 'List automations',
        description: 'Each row carries `runs` (DMs and public replies it sent) and `ctr` (clicks ÷ DMs sent with a button; 0 while none had one), for the list’s Runs and CTR columns. `meta.total` counts every automation the filters match; `meta.live` counts how many of those are live, whatever `status` asks for ("3 live of 5").',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [
            new OA\Parameter(name: 'channel_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive'])),
            new OA\Parameter(name: 'folder_id', in: 'query', required: false, description: 'Only the automations in this folder', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Automations whose name contains this text, ignoring case', schema: new OA\Schema(type: 'string', maxLength: 100)),
            new OA\Parameter(name: 'trashed', in: 'query', required: false, description: 'true lists the Trash (S5.3) instead, last deleted first; each has `trashed_at` and `purges_at`', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Automation list')]
    )]
    public function index(Request $request): AutomationCollection
    {
        $query = $request->user()->automations()->with(['channel', 'template'])->withListFigures();

        if ($request->boolean('trashed')) {
            $query->onlyTrashed()->latest('deleted_at')->orderByDesc('id');
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->filled('folder_id')) {
            $query->where('automation_folder_id', $request->integer('folder_id'));
        }

        if ($request->filled('search')) {
            $query->whereLike('name', '%'.mb_substr(trim($request->string('search')), 0, 100).'%');
        }

        $live = (clone $query)->where('active', true)->count();

        if ($request->filled('status')) {
            $query->where('active', $request->status === 'active');
        }

        return (new AutomationCollection($query->paginate($request->integer('per_page', 20))))
            ->additional(['meta' => ['live' => $live]]);
    }

    #[OA\Post(
        path: '/api/v1/automations',
        summary: 'Create a new automation',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        responses: [
            new OA\Response(response: 201, description: 'Automation created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreAutomationRequest $request): JsonResponse
    {
        abort_unless(
            $request->user()->channels()->where('id', $request->channel_id)->exists(),
            403
        );

        $automation = $request->user()->automations()->create($request->validated());

        return response()->json(['data' => new AutomationResource($automation)], 201);
    }

    #[OA\Get(
        path: '/api/v1/automations/{id}',
        summary: 'Get automation detail',
        description: 'Adds `runs`, `ctr` and the `post` it watches (null for DM and story automations, or when it watches every post), for the Preview tab.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Automation detail')]
    )]
    public function show(Request $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $automation = Automation::withListFigures()->with('post')->findOrFail($automation->id);

        return response()->json(['data' => new AutomationResource($automation)]);
    }

    #[OA\Patch(
        path: '/api/v1/automations/{id}',
        summary: 'Update an automation',
        description: 'Also moves it into a folder: send `automation_folder_id`, or null to take it out (drag and drop on the list).',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 255),
            new OA\Property(property: 'automation_folder_id', type: 'integer', nullable: true, description: 'One of the owner’s folders, or null'),
        ], example: ['automation_folder_id' => 3])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(UpdateAutomationRequest $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $data = $request->validated();
        if (array_key_exists('extras', $data)) {
            $data['extras'] = array_merge($automation->extras ?? [], $data['extras']);
        }
        $automation->update($data);

        return response()->json(['data' => new AutomationResource($automation->fresh())]);
    }

    #[OA\Delete(
        path: '/api/v1/automations/{id}',
        summary: 'Move an automation to the Trash',
        description: 'Stops it and moves it to the Trash, where it is kept for 30 days (`purges_at`) and then deleted for good. Undo and the Trash’s Restore call POST /api/v1/automations/{id}/restore.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'In the Trash; `data` is the automation with `trashed_at` and `purges_at`'),
            new OA\Response(response: 403, description: 'Another owner’s automation'),
            new OA\Response(response: 404, description: 'No such automation, or it is already in the Trash'),
        ]
    )]
    public function destroy(Request $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $automation->moveToTrash();

        return response()->json(['message' => 'Automation moved to the Trash.', 'data' => new AutomationResource($automation)]);
    }

    #[OA\Post(
        path: '/api/v1/automations/{id}/restore',
        summary: 'Restore an automation from the Trash',
        description: 'From the toast’s Undo and the Trash’s Restore. It comes back stopped: restoring does not start it replying again.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Back in the list, stopped'),
            new OA\Response(response: 403, description: 'Another owner’s automation'),
            new OA\Response(response: 404, description: 'No such automation in the Trash'),
        ]
    )]
    public function restore(Request $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);
        abort_unless($automation->trashed(), 404);

        $automation->restore();

        return response()->json(['data' => new AutomationResource($automation)]);
    }

    #[OA\Patch(
        path: '/api/v1/automations/{id}/toggle',
        summary: 'Enable or disable an automation',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['active'],
                properties: [new OA\Property(property: 'active', type: 'boolean')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status updated'),
            new OA\Response(response: 422, description: 'Starting it was refused: the channel is disconnected or the plan’s live automations are all in use', content: new OA\JsonContent(ref: '#/components/schemas/GoLiveRefusal')),
        ]
    )]
    public function toggle(ToggleAutomationRequest $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        if ($request->boolean('active') && ! $automation->active) {
            $refusal = $request->user()->goLiveRefusal($automation->channel, $automation);

            if ($refusal !== null) {
                return response()->json($refusal, 422);
            }
        }

        $automation->update(['active' => $request->boolean('active')]);

        return response()->json(['data' => ['id' => $automation->id, 'active' => $automation->active]]);
    }

    #[OA\Patch(
        path: '/api/v1/automations/{id}/ai',
        summary: 'Turn Blexchat AI on or off for one automation',
        description: 'On plans that include Blexchat AI (`has_ai` on the plan). Turning it off always works, so a downgraded account can still switch it off. With it on, Blexchat AI answers what no keyword of this automation matches, from the business info in `/ai/settings`, and hands over when unsure.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['enabled'], properties: [
            new OA\Property(property: 'enabled', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Saved', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'ai_enabled', type: 'boolean'),
                ]),
            ])),
            new OA\Response(response: 403, description: 'Another owner’s automation; or `code: plan_required` when the plan does not include Blexchat AI (show Upgrade)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'code', type: 'string', enum: ['plan_required']),
            ])),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function ai(ToggleAutomationAiRequest $request, Automation $automation): JsonResponse
    {
        $enabled = $request->boolean('enabled');

        if ($enabled && ! $request->user()->currentPlan()->has_ai) {
            return response()->json(['message' => 'Blexchat AI is not included in your plan.', 'code' => 'plan_required'], 403);
        }

        $automation->update(['ai_enabled' => $enabled]);

        return response()->json(['data' => ['id' => $automation->id, 'ai_enabled' => $automation->ai_enabled]]);
    }

    #[OA\Post(
        path: '/api/v1/automations/wizard',
        summary: 'Save a new automation from the wizard (Go live on Review)',
        description: 'Takes the wizard’s answers and stores the automation live. A paid answer (post_mode any or next, ask_follow, ask_email, follow_up) needs a paid plan or a running trial.',
        security: [['sanctum' => []]],
        tags: ['Automation wizard'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the error messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/WizardAnswers', example: [
            'name' => 'Comment a word → send the catalogue', 'channel_id' => 1, 'kind' => 'comment', 'template' => 't1',
            'post_mode' => 'specific', 'post_id' => 1, 'trigger' => 'words', 'words' => ['كتالوج'],
            'public_reply' => true, 'public_reply_text' => 'أرسلنا لك التفاصيل بالخاص 💌',
            'dm_text' => 'هلا والله! 👋 هذا كتالوج الموسم كامل، بالأسعار والمقاسات:', 'dm_button' => 'افتحي الكتالوج',
            'link_url' => 'https://nourboutique.com/abaya-winter',
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Saved and live; `data.wizard` holds the answers back'),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: 'A missing or invalid answer, another account’s channel or post, or a paid answer on Free (ValidationError); or, with `code`, the channel is disconnected or the plan’s live automations are all in use (the wizard’s "Couldn’t go live")', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: '#/components/schemas/ValidationError'), new OA\Schema(ref: '#/components/schemas/GoLiveRefusal')])),
        ]
    )]
    public function storeFromWizard(StoreWizardAutomationRequest $request): JsonResponse
    {
        $refusal = $request->user()->goLiveRefusal(Channel::findOrFail($request->integer('channel_id')));

        if ($refusal !== null) {
            return response()->json($refusal, 422);
        }

        $automation = $request->user()->automations()->create([
            ...$request->automationAttributes(),
            'channel_id' => $request->integer('channel_id'),
            'automation_template_id' => $request->template()?->id,
        ]);

        return response()->json(['data' => new AutomationResource($automation)], 201);
    }

    #[OA\Put(
        path: '/api/v1/automations/{id}/wizard',
        summary: 'Save an edit made in the wizard',
        description: 'Send every answer again, as on create. The kind and the channel stay as saved. A paid answer already saved stays allowed after a trial ends; switching a new one on needs a paid plan.',
        security: [['sanctum' => []]],
        tags: ['Automation wizard'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the error messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar'])),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/WizardAnswers')),
        responses: [
            new OA\Response(response: 200, description: 'Saved'),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another account’s automation'),
            new OA\Response(response: 422, description: 'A missing or invalid answer', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function updateFromWizard(UpdateWizardAutomationRequest $request, Automation $automation): JsonResponse
    {
        $automation->update($request->automationAttributes());

        return response()->json(['data' => new AutomationResource($automation->fresh())]);
    }
}
