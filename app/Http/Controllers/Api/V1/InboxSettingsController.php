<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inbox\UpdateInboxSettingsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'InboxSettings', properties: [
    new OA\Property(property: 'accepting_new', type: 'boolean', example: true, description: 'The inbox’s "Accept new conversations" switch. While off, automations do not open a conversation with someone new: their messages still arrive in the inbox, unanswered, and existing conversations carry on as before.'),
])]
class InboxSettingsController extends Controller
{
    #[OA\Get(
        path: '/api/v1/inbox/settings',
        summary: 'Inbox settings',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/InboxSettings'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        return $this->settings($request->user()->account());
    }

    #[OA\Patch(
        path: '/api/v1/inbox/settings',
        summary: 'Change inbox settings',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/InboxSettings')),
        responses: [
            new OA\Response(response: 200, description: 'Saved', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/InboxSettings'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateInboxSettingsRequest $request): JsonResponse
    {
        $request->user()->update(['inbox_accepting_new' => $request->boolean('accepting_new')]);

        return $this->settings($request->user());
    }

    private function settings(User $user): JsonResponse
    {
        return response()->json(['data' => ['accepting_new' => $user->inbox_accepting_new]]);
    }
}
