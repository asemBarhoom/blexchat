<?php

namespace App\Services\Channels;

use App\Models\MessageDeliveryAttempt;
use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * The one path a failed reply takes to be sent again, from the API and the admin panel alike.
 */
class DeliveryRetrier
{
    public function __construct(private readonly InstagramClient $instagram, private readonly MessagingClient $messaging) {}

    public function retry(MessageDeliveryAttempt $attempt): DeliveryRetryResult
    {
        $conversation = $attempt->conversation()->with('channel')->firstOrFail();

        if ($attempt->status === 'sent') {
            return new DeliveryRetryResult(sent: false, alreadySent: true);
        }

        $attempt->increment('attempts');
        $attempt->update(['last_attempt_at' => now()]);

        try {
            $platformMessageId = $conversation->channel->platform === 'instagram'
                ? $this->instagram->sendMessage($conversation->channel, $conversation->contact_platform_id, InstagramClient::message($attempt->content))
                : $this->messaging->send($conversation->channel, $conversation->contact_platform_id, $attempt->content);
        } catch (RequestException|ConnectionException|ChannelNotLive $exception) {
            $code = $exception instanceof ChannelNotLive ? 'channel_not_live' : 'provider_error';
            $attempt->update(['error_code' => $code, 'error_message' => $exception->getMessage()]);

            return new DeliveryRetryResult(sent: false, errorCode: $code);
        }

        $attempt->update(['status' => 'sent', 'error_code' => null, 'error_message' => null]);

        // The reply the owner wrote reaches the inbox now, as it would have on the first send.
        $message = $conversation->messages()->create([
            'platform_message_id' => $platformMessageId,
            'direction' => 'outbound',
            'type' => 'text',
            'content' => $attempt->content,
            'automated' => false,
            'sent_at' => now(),
        ]);
        $conversation->update(['last_message' => $attempt->content, 'last_message_at' => now()]);
        $conversation->markHandledByOwner();

        return new DeliveryRetryResult(sent: true, message: $message, platformMessageId: $platformMessageId);
    }
}
