<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Workspace\SaveWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Workspace', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 4),
    new OA\Property(property: 'name', type: 'string', example: 'Lina’s Bakery'),
    new OA\Property(property: 'channels_count', type: 'integer', example: 2),
    new OA\Property(property: 'current', type: 'boolean', description: 'The workspace this request worked in'),
])]
class WorkspaceController extends Controller
{
    #[OA\Get(
        path: '/api/v1/workspaces',
        summary: 'The owner’s workspaces (BE-11)',
        description: 'Each business an owner runs. Send the chosen one’s id as the `X-Workspace` header on every request; without it the first workspace is used. The plan and its limits cover every workspace together.',
        security: [['sanctum' => []]],
        tags: ['Workspaces'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Workspace')),
                new OA\Property(property: 'meta', properties: [
                    new OA\Property(property: 'used', type: 'integer', example: 2),
                    new OA\Property(property: 'limit', type: 'integer', example: 10),
                ], type: 'object'),
            ])),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->workspace();
        $workspaces = $user->workspaces()->withCount('channels')->oldest('id')->get();

        return response()->json([
            'data' => $workspaces->map(fn (Workspace $workspace): array => $this->present($workspace, $user))->all(),
            'meta' => ['used' => $workspaces->count(), 'limit' => $user->currentPlan()->workspaces_limit],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/workspaces',
        summary: 'Add a workspace',
        security: [['sanctum' => []]],
        tags: ['Workspaces'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 80),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Workspace')])),
            new OA\Response(response: 403, description: '`code: workspace_limit` when the plan’s workspaces are all in use (show Upgrade)'),
            new OA\Response(response: 422, description: 'No name', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(SaveWorkspaceRequest $request): JsonResponse
    {
        $user = $request->user();
        $limit = $user->currentPlan()->workspaces_limit;

        if ($user->workspaces()->count() >= $limit) {
            return response()->json(['message' => trans_choice('workspaces.limit', $limit, ['count' => $limit]), 'code' => 'workspace_limit', 'limit' => $limit], 403);
        }

        $workspace = $user->workspaces()->create($request->validated());

        return response()->json(['data' => $this->present($workspace->loadCount('channels'), $user)], 201);
    }

    #[OA\Patch(
        path: '/api/v1/workspaces/{id}',
        summary: 'Rename a workspace',
        security: [['sanctum' => []]],
        tags: ['Workspaces'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 80),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Renamed', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Workspace')])),
            new OA\Response(response: 403, description: 'Not your workspace'),
        ],
    )]
    public function update(SaveWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $workspace->update($request->validated());

        return response()->json(['data' => $this->present($workspace->loadCount('channels'), $request->user())]);
    }

    #[OA\Delete(
        path: '/api/v1/workspaces/{id}',
        summary: 'Delete a workspace and everything in it',
        description: 'Its channels, automations, folders, contacts and invitations go with it; its teammates lose access. The only workspace cannot be deleted.',
        security: [['sanctum' => []]],
        tags: ['Workspaces'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not your workspace'),
            new OA\Response(response: 409, description: '`code: last_workspace`'),
        ],
    )]
    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();
        abort_unless($workspace->owner_id === $user->id, 404);

        if ($user->workspaces()->count() <= 1) {
            return response()->json(['message' => __('workspaces.last'), 'code' => 'last_workspace'], 409);
        }

        $workspace->deleteWithTeam();

        return response()->json(['message' => __('workspaces.deleted')]);
    }

    /**
     * @return array{id: int, name: string, channels_count: int, current: bool}
     */
    private function present(Workspace $workspace, User $user): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'channels_count' => (int) $workspace->channels_count,
            'current' => $workspace->is($user->workspace()),
        ];
    }
}
