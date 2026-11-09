<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageDeliveryAttempt;
use App\Services\Channels\MessagingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageDeliveryAttemptController extends Controller
{
    public function retry(Request $request, MessageDeliveryAttempt $attempt, MessagingClient $messaging): JsonResponse
    {
        $conversation = $attempt->conversation()->with('channel')->firstOrFail();
        abort_unless($conversation->user_id === $request->user()->id, 403);
        if ($attempt->status === 'sent') {
            return response()->json(['data' => ['status' => 'sent', 'idempotent' => true]]);
        }
        $attempt->increment('attempts');
        $attempt->update(['last_attempt_at' => now()]);
        try {
            $id = $messaging->send($conversation->channel, $conversation->contact_platform_id, $attempt->content);
            $attempt->update(['status' => 'sent', 'error_code' => null, 'error_message' => null]);

            return response()->json(['data' => ['status' => 'sent', 'platform_message_id' => $id]]);
        } catch (\Throwable $e) {
            $attempt->update(['error_code' => 'provider_error', 'error_message' => $e->getMessage()]);

            return response()->json(['message' => 'Delivery failed again.', 'code' => 'provider_error', 'retryable' => true], 502);
        }
    }
}
