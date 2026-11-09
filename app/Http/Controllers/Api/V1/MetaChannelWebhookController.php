<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaChannelWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

/**
 * Meta's webhooks for WhatsApp (Cloud API) and Messenger, on the Meta app set in META_APP_SECRET.
 */
class MetaChannelWebhookController extends Controller
{
    /** The webhook's `object` per platform. */
    private const OBJECTS = ['whatsapp' => 'whatsapp_business_account', 'messenger' => 'page'];

    #[OA\Get(
        path: '/api/v1/webhooks/{platform}',
        summary: 'Meta’s webhook verification for WhatsApp or Messenger',
        tags: ['Webhooks'],
        parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['whatsapp', 'messenger']))],
        responses: [
            new OA\Response(response: 200, description: 'The hub.challenge, as plain text'),
            new OA\Response(response: 403, description: 'Wrong verify token'),
        ]
    )]
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.meta.webhook_verify_token');
        $valid = $request->query('hub_mode') === 'subscribe'
            && $expected !== ''
            && hash_equals($expected, (string) $request->query('hub_verify_token'));

        abort_unless($valid, 403);

        return response((string) $request->query('hub_challenge'));
    }

    #[OA\Post(
        path: '/api/v1/webhooks/{platform}',
        summary: 'Receive WhatsApp or Messenger messages',
        description: 'Requires a valid X-Hub-Signature-256 header. Messages land in the inbox in the background.',
        tags: ['Webhooks'],
        parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['whatsapp', 'messenger']))],
        responses: [
            new OA\Response(response: 200, description: 'Received'),
            new OA\Response(response: 403, description: 'Missing or wrong signature'),
        ]
    )]
    public function receive(Request $request, string $platform): JsonResponse
    {
        $secret = (string) config('services.meta.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        $signatureValid = $secret !== '' && hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
        if (! $signatureValid) {
            WebhookEvent::firstOrCreate(['event_key' => $platform.':rejected:'.sha1($request->getContent())], ['provider' => $platform, 'status' => 'rejected', 'object' => $request->input('object'), 'payload' => $request->input('entry', []), 'error' => 'invalid_signature']);
        }
        abort_unless($signatureValid, 403);

        if ($request->input('object') === self::OBJECTS[$platform]) {
            $key = 'webhook:meta:'.sha1($request->getContent());
            $event = WebhookEvent::firstOrCreate(['event_key' => $platform.':'.sha1($request->getContent())], ['provider' => $platform, 'status' => 'received', 'object' => $request->input('object'), 'payload' => $request->input('entry', [])]);
            if ($event->wasRecentlyCreated && Cache::add($key, true, now()->addMinutes(10))) {
                ProcessMetaChannelWebhook::dispatch($platform, $request->input('entry', []));
            }
        }

        return response()->json(['status' => 'received']);
    }
}
