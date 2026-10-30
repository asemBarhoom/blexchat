<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Webhooks', description: 'Receive real-time events from Meta and TikTok')]
class WebhookController extends Controller
{
    #[OA\Get(
        path: '/api/v1/webhooks/meta',
        summary: 'Meta webhook verification challenge',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'hub.mode', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hub.verify_token', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hub.challenge', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Returns hub.challenge as plain text'),
            new OA\Response(response: 403, description: 'Invalid verify token'),
        ]
    )]
    public function verifyMeta(Request $request): Response|JsonResponse
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.meta.webhook_token')) {
            return response($challenge, 200);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }

    #[OA\Post(
        path: '/api/v1/webhooks/meta',
        summary: 'Receive Meta platform events (comments, DMs, reactions)',
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['object', 'entry'],
                properties: [
                    new OA\Property(property: 'object', type: 'string', example: 'instagram'),
                    new OA\Property(property: 'entry', type: 'array', items: new OA\Items(type: 'object')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Event acknowledged')]
    )]
    public function meta(Request $request): JsonResponse
    {
        $signature = $request->header('X-Hub-Signature-256');
        $expected  = 'sha256='.hash_hmac('sha256', $request->getContent(), config('services.meta.app_secret', ''));

        if ($signature && ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        return response()->json(['status' => 'ok']);
    }

    #[OA\Post(
        path: '/api/v1/webhooks/tiktok',
        summary: 'Receive TikTok platform events',
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['event', 'data'],
                properties: [
                    new OA\Property(property: 'event', type: 'string', example: 'comment'),
                    new OA\Property(property: 'data', type: 'object'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Event acknowledged')]
    )]
    public function tiktok(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
