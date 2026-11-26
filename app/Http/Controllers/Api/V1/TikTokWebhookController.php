<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTikTokWebhook;
use App\Models\WebhookEvent;
use App\Services\TikTok\TikTokClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * TikTok for Business webhooks: direct messages (DIRECT_MESSAGE) and comments (COMMENT), for the
 * app set in TIKTOK_CLIENT_ID and TIKTOK_CLIENT_SECRET. Subscribed with `php artisan tiktok:subscribe-webhooks`.
 */
class TikTokWebhookController extends Controller
{
    #[OA\Post(
        path: '/api/v1/webhooks/tiktok',
        summary: 'Receive TikTok direct messages and comments',
        description: 'Requires a valid TikTok-Signature header (t=<timestamp>,s=<HMAC-SHA256 of "<timestamp>.<body>">) no older than five minutes. TikTok delivers at least once: an event that comes again is acknowledged and not handled twice.',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Received'),
            new OA\Response(response: 403, description: 'Missing, wrong or expired signature'),
        ]
    )]
    public function receive(Request $request): JsonResponse
    {
        $body = $request->getContent();

        if (! TikTokClient::validSignature($request->header('TikTok-Signature'), $body)) {
            WebhookEvent::firstOrCreate(['event_key' => 'tiktok:rejected:'.sha1($body)], ['provider' => 'tiktok', 'status' => 'rejected', 'object' => $request->input('event'), 'payload' => $request->all(), 'error' => 'invalid_signature']);

            abort(403);
        }

        // The same event can come again (a retry has a new signature, not a new event): kept once, handled once.
        $identity = implode('|', [$request->input('event'), $request->input('user_openid'), $request->input('create_time'), $request->input('content')]);
        $event = WebhookEvent::firstOrCreate(['event_key' => 'tiktok:'.sha1($identity)], ['provider' => 'tiktok', 'status' => 'received', 'object' => $request->input('event'), 'payload' => $request->all()]);

        if ($event->wasRecentlyCreated) {
            ProcessTikTokWebhook::dispatch($request->all());
        }

        return response()->json(['status' => 'received']);
    }
}
