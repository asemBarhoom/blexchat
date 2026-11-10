<?php

namespace App\Services\Channels;

use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp, Messenger and TikTok for development (MESSAGING_DRIVER=fake): replies are logged, not
 * sent. Incoming messages are simulated with POST /api/v1/channels/{channel}/test-events.
 */
class FakeMessagingClient extends MessagingClient
{
    public function send(Channel $channel, string $recipientId, string $text): ?string
    {
        Log::info("Fake {$channel->platform} reply", ['channel_id' => $channel->id, 'to' => $recipientId, 'text' => $text]);

        return 'fake_'.Str::random(16);
    }

    public function replyToComment(Channel $channel, string $commentId, string $text): ?string
    {
        Log::info("Fake {$channel->platform} public comment reply", ['channel_id' => $channel->id, 'comment_id' => $commentId, 'text' => $text]);

        return 'fake_comment_'.Str::random(16);
    }

    public function sendPrivateReply(Channel $channel, string $commentId, string $text): ?string
    {
        Log::info("Fake {$channel->platform} private reply", ['channel_id' => $channel->id, 'comment_id' => $commentId, 'text' => $text]);

        return 'fake_'.Str::random(16);
    }
}
