<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\MessageDeliveryAttempt;
use App\Services\Channels\DeliveryRetrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MessageDeliveryAttemptController extends Controller
{
    #[OA\Post(
        path: '/api/v1/message-delivery-attempts/{attempt}/retry',
        summary: 'Retry a failed delivery',
        description: 'Safe to call twice: an attempt already sent answers `idempotent: true` and sends nothing. `attempts` counts the sends made for it, starting at 1. A send that goes through is added to the conversation like any reply, and comes back as `data.message`.',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [new OA\Parameter(name: 'attempt', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'The `attempt_id` returned by a failed send')],
        responses: [
            new OA\Response(response: 200, description: 'Sent, or already sent; `data` has `attempt_id`, `status`, `attempts`, `platform_message_id` and, on the send that went through, `message`'),
            new OA\Response(response: 403, description: 'The conversation belongs to another account'),
            new OA\Response(response: 502, description: 'Delivery failed again; `code` is `channel_not_live` or `provider_error`, with `retryable`, `attempt_id` and `attempts`'),
        ]
    )]
    public function retry(Request $request, MessageDeliveryAttempt $attempt, DeliveryRetrier $retrier): JsonResponse
    {
        $conversation = $attempt->conversation()->with('channel')->firstOrFail();

        // A conversation has no owner of its own: the channel it belongs to names the account.
        abort_unless($request->user()->workspace()->channels()->whereKey($conversation->channel_id)->exists(), 403);

        $result = $retrier->retry($attempt);

        if ($result->alreadySent) {
            return response()->json(['data' => ['attempt_id' => $attempt->id, 'status' => 'sent', 'attempts' => $attempt->attempts, 'idempotent' => true]]);
        }

        if (! $result->sent) {
            return response()->json([
                'message' => $result->errorCode === 'channel_not_live' ? __('channels.not_live', ['platform' => __("channels.platforms.{$conversation->channel->platform}")]) : __('channels.delivery_failed_again'),
                'code' => $result->errorCode,
                'retryable' => $result->errorCode !== 'channel_not_live',
                'attempt_id' => $attempt->id,
                'attempts' => $attempt->attempts,
            ], 502);
        }

        return response()->json(['data' => [
            'attempt_id' => $attempt->id,
            'status' => 'sent',
            'attempts' => $attempt->attempts,
            'platform_message_id' => $result->platformMessageId,
            'message' => new MessageResource($result->message),
        ]]);
    }
}
