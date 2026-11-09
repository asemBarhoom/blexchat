<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageDeliveryAttempt;
use App\Services\Channels\ChannelNotLive;
use App\Services\Channels\MessagingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MessageDeliveryAttemptController extends Controller
{
    #[OA\Post(
        path: '/api/v1/message-delivery-attempts/{attempt}/retry',
        summary: 'Retry a failed delivery',
        description: 'Safe to call twice: an attempt already sent answers `idempotent: true` and sends nothing. `attempts` counts the sends made for it, starting at 1.',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [new OA\Parameter(name: 'attempt', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'The `attempt_id` returned by a failed send')],
        responses: [
            new OA\Response(response: 200, description: 'Sent, or already sent; `data` has `attempt_id`, `status`, `attempts` and `platform_message_id`'),
            new OA\Response(response: 403, description: 'The conversation belongs to another account'),
            new OA\Response(response: 502, description: 'Delivery failed again; `code` is `channel_not_live` or `provider_error`, with `retryable`, `attempt_id` and `attempts`'),
        ]
    )]
    public function retry(Request $request, MessageDeliveryAttempt $attempt, MessagingClient $messaging): JsonResponse
    {
        $conversation = $attempt->conversation()->with('channel')->firstOrFail();

        // A conversation has no owner of its own: the channel it belongs to names the account.
        abort_unless($request->user()->account()->channels()->whereKey($conversation->channel_id)->exists(), 403);

        if ($attempt->status === 'sent') {
            return response()->json(['data' => ['attempt_id' => $attempt->id, 'status' => 'sent', 'attempts' => $attempt->attempts, 'idempotent' => true]]);
        }

        $attempt->increment('attempts');
        $attempt->update(['last_attempt_at' => now()]);

        try {
            $id = $messaging->send($conversation->channel, $conversation->contact_platform_id, $attempt->content);
            $attempt->update(['status' => 'sent', 'error_code' => null, 'error_message' => null]);

            return response()->json(['data' => ['attempt_id' => $attempt->id, 'status' => 'sent', 'attempts' => $attempt->attempts, 'platform_message_id' => $id]]);
        } catch (\Throwable $exception) {
            $code = $exception instanceof ChannelNotLive ? 'channel_not_live' : 'provider_error';
            $attempt->update(['error_code' => $code, 'error_message' => $exception->getMessage()]);

            return response()->json([
                'message' => $code === 'channel_not_live' ? $exception->getMessage() : 'Delivery failed again.',
                'code' => $code,
                'retryable' => $code !== 'channel_not_live',
                'attempt_id' => $attempt->id,
                'attempts' => $attempt->attempts,
            ], 502);
        }
    }
}
