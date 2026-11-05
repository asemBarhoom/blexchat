<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AutomationFolder\SaveAutomationFolderRequest;
use App\Models\AutomationFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AutomationFolder', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 3),
    new OA\Property(property: 'name', type: 'string', example: 'Ramadan'),
    new OA\Property(property: 'automations_count', type: 'integer', example: 2, description: 'Automations in it, outside the Trash'),
])]
class AutomationFolderController extends Controller
{
    #[OA\Get(
        path: '/api/v1/automation-folders',
        summary: 'The owner’s automation folders, by name',
        description: 'The folder tabs above the automations list. "All" is not a folder: it is the list without `folder_id`.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AutomationFolder')),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $folders = $request->user()->automationFolders()->withCount('automations')->orderBy('name')->get();

        return response()->json(['data' => $folders->map(fn (AutomationFolder $folder): array => $this->present($folder))->all()]);
    }

    #[OA\Post(
        path: '/api/v1/automation-folders',
        summary: 'Create folder',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 60, example: 'Summer sale'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AutomationFolder')])),
            new OA\Response(response: 422, description: 'No name, a name already used, or 50 folders already', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(SaveAutomationFolderRequest $request): JsonResponse
    {
        $folder = $request->user()->automationFolders()->create($request->validated());

        return response()->json(['data' => $this->present($folder->loadCount('automations'))], 201);
    }

    #[OA\Patch(
        path: '/api/v1/automation-folders/{id}',
        summary: 'Rename a folder',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 60),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Renamed', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AutomationFolder')])),
            new OA\Response(response: 403, description: 'Another owner’s folder'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(SaveAutomationFolderRequest $request, AutomationFolder $folder): JsonResponse
    {
        $folder->update($request->validated());

        return response()->json(['data' => $this->present($folder->loadCount('automations'))]);
    }

    #[OA\Delete(
        path: '/api/v1/automation-folders/{id}',
        summary: 'Delete a folder',
        description: 'Its automations stay, outside any folder; none is stopped or moved to the Trash.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Another owner’s folder'),
        ],
    )]
    public function destroy(Request $request, AutomationFolder $folder): JsonResponse
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $folder->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{id: int, name: string, automations_count: int}
     */
    private function present(AutomationFolder $folder): array
    {
        return ['id' => $folder->id, 'name' => $folder->name, 'automations_count' => (int) $folder->automations_count];
    }
}
