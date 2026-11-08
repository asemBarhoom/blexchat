<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\UpdateAiSettingsRequest;
use App\Models\User;
use App\Services\Ai\ClaudeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AiSettings', properties: [
    new OA\Property(property: 'business_info', type: 'string', nullable: true, maxLength: 5000, example: 'We sell abayas in sizes 36–48. Delivery in Riyadh takes 2–4 days, 25 SAR. Exchanges within 7 days, unworn.', description: 'What Blexchat AI answers from: products, sizes, prices, delivery, policies, hours. It answers nothing this does not say; it hands the conversation over instead.'),
    new OA\Property(property: 'included', type: 'boolean', example: true, description: 'The plan includes Blexchat AI (`has_ai`); otherwise show Upgrade'),
    new OA\Property(property: 'live', type: 'boolean', example: true, description: 'The AI engine is set up on the server; while false nothing is answered and conversations are handed over as before'),
])]
class AiSettingsController extends Controller
{
    #[OA\Get(
        path: '/api/v1/ai/settings',
        summary: 'Blexchat AI settings',
        description: 'Blexchat AI answers DMs that no keyword of the automation handling the conversation matches, for automations with AI on (`PATCH /automations/{id}/ai`). When the business info does not answer the question it hands over to the inbox with a one-line reason (the handover message’s `reason`). Its replies have `by_ai: true`.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AiSettings'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function show(Request $request, ClaudeClient $claude): JsonResponse
    {
        return $this->settings($request->user(), $claude);
    }

    #[OA\Patch(
        path: '/api/v1/ai/settings',
        summary: 'Save the business info Blexchat AI answers from',
        description: 'Works on every plan, so the owner can write it before upgrading.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['business_info'], properties: [
            new OA\Property(property: 'business_info', type: 'string', nullable: true, maxLength: 5000),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Saved', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AiSettings'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateAiSettingsRequest $request, ClaudeClient $claude): JsonResponse
    {
        $request->user()->update(['business_info' => filled($request->input('business_info')) ? trim($request->input('business_info')) : null]);

        return $this->settings($request->user(), $claude);
    }

    private function settings(User $user, ClaudeClient $claude): JsonResponse
    {
        return response()->json(['data' => [
            'business_info' => $user->business_info,
            'included' => (bool) $user->currentPlan()->has_ai,
            'live' => $claude->available(),
        ]]);
    }
}
