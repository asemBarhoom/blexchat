<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Automation\StoreAutomationRequest;
use App\Http\Requests\Api\Automation\ToggleAutomationRequest;
use App\Http\Requests\Api\Automation\UpdateAutomationRequest;
use App\Http\Resources\Api\V1\AutomationCollection;
use App\Http\Resources\Api\V1\AutomationResource;
use App\Models\Automation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Automations', description: 'Create and manage keyword-triggered automation rules')]
class AutomationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/automations',
        summary: 'List automations',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [
            new OA\Parameter(name: 'channel_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Automation list')]
    )]
    public function index(Request $request): AutomationCollection
    {
        $query = $request->user()->automations()->with('channel');

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->filled('status')) {
            $query->where('active', $request->status === 'active');
        }

        return new AutomationCollection($query->paginate($request->integer('per_page', 20)));
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
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Automation detail')]
    )]
    public function show(Request $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        return response()->json(['data' => new AutomationResource($automation)]);
    }

    #[OA\Patch(
        path: '/api/v1/automations/{id}',
        summary: 'Update an automation',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(UpdateAutomationRequest $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $automation->update($request->validated());

        return response()->json(['data' => new AutomationResource($automation->fresh())]);
    }

    #[OA\Delete(
        path: '/api/v1/automations/{id}',
        summary: 'Delete an automation',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(Request $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $automation->delete();

        return response()->json(['message' => 'Automation deleted.']);
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
        responses: [new OA\Response(response: 200, description: 'Status updated')]
    )]
    public function toggle(ToggleAutomationRequest $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $automation->update(['active' => $request->boolean('active')]);

        return response()->json(['data' => ['id' => $automation->id, 'active' => $automation->active]]);
    }
}
