<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInstagramWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Webhooks', description: 'Events Meta sends about connected Instagram accounts')]
class InstagramWebhookController extends Controller
{
    #[OA\Get(
        path: '/api/v1/webhooks/instagram',
        summary: 'Meta webhook verification',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'hub.mode', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hub.verify_token', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hub.challenge', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Echoes hub.challenge'),
            new OA\Response(response: 403, description: 'Wrong verify token'),
        ]
    )]
    public function verify(Request $request): Response
    {
        // PHP turns the dots in hub.mode etc. into underscores.
        $expected = (string) config('services.instagram.webhook_verify_token');
        $valid = $request->query('hub_mode') === 'subscribe'
            && $expected !== ''
            && hash_equals($expected, (string) $request->query('hub_verify_token'));

        abort_unless($valid, 403);

        return response((string) $request->query('hub_challenge'));
    }

    #[OA\Post(
        path: '/api/v1/webhooks/instagram',
        summary: 'Receive Instagram comments, messages and story replies',
        description: 'Requires a valid X-Hub-Signature-256 header. Events are processed in the background; replies from matching automations are sent from there.',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
            new OA\Response(response: 403, description: 'Missing or invalid signature'),
        ]
    )]
    public function receive(Request $request): JsonResponse
    {
        $secret = (string) config('services.instagram.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        $signatureValid = $secret !== '' && hash_equals($expected, $signature);
        if (! $signatureValid) {
            WebhookEvent::firstOrCreate(['event_key' => 'instagram:rejected:'.sha1($request->getContent())], ['provider' => 'instagram', 'status' => 'rejected', 'object' => $request->input('object'), 'payload' => $request->input('entry', []), 'error' => 'invalid_signature']);
        }
        abort_unless($signatureValid, 403);

        if ($request->input('object') === 'instagram') {
            $key = 'webhook:instagram:'.sha1($request->getContent());
            $event = WebhookEvent::firstOrCreate(['event_key' => 'instagram:'.sha1($request->getContent())], ['provider' => 'instagram', 'status' => 'received', 'object' => 'instagram', 'payload' => $request->input('entry', [])]);
            if ($event->wasRecentlyCreated && Cache::add($key, true, now()->addMinutes(10))) {
                ProcessInstagramWebhook::dispatch($request->input('entry', []));
            }
        }

        return response()->json(['status' => 'received']);
    }
}
