<?php

namespace App\Services\Channels;

use App\Models\Channel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Sends a text reply on WhatsApp (Cloud API), Messenger (Send API) or TikTok, over the channel's
 * own token. Instagram has InstagramClient. Sends are never retried, so nobody gets a reply twice.
 * MESSAGING_DRIVER=fake (the default on local) logs instead of sending.
 */
class MessagingClient
{
    public const PLATFORMS = ['whatsapp', 'messenger', 'tiktok'];

    public static function fakeActive(): bool
    {
        return config('services.channels.driver') === 'fake' && ! app()->isProduction();
    }

    /**
     * The id the platform gave the sent message, when it gives one.
     *
     * @throws RequestException|ConnectionException|ChannelNotLive
     */
    public function send(Channel $channel, string $recipientId, string $text): ?string
    {
        return match ($channel->platform) {
            'whatsapp' => $this->graph($channel)
                ->post("{$channel->account_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipientId,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ])->throw()->json('messages.0.id'),
            'messenger' => $this->graph($channel)
                ->post('me/messages', [
                    'recipient' => ['id' => $recipientId],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => $text],
                ])->throw()->json('message_id'),
            // TikTok's business messaging is not open to apps yet: it works on the fake driver only.
            default => throw new ChannelNotLive($channel->platform),
        };
    }

    /**
     * A public reply under a comment. Facebook Pages only: TikTok's comment API is not open to apps yet.
     *
     * @throws RequestException|ConnectionException|ChannelNotLive
     */
    public function replyToComment(Channel $channel, string $commentId, string $text): ?string
    {
        if ($channel->platform !== 'messenger') {
            throw new ChannelNotLive($channel->platform);
        }

        return $this->graph($channel)->post("{$commentId}/comments", ['message' => $text])->throw()->json('id');
    }

    /**
     * A private message to whoever wrote a comment on the Page, sent through Messenger.
     *
     * @throws RequestException|ConnectionException|ChannelNotLive
     */
    public function sendPrivateReply(Channel $channel, string $commentId, string $text): ?string
    {
        if ($channel->platform !== 'messenger') {
            throw new ChannelNotLive($channel->platform);
        }

        return $this->graph($channel)
            ->post('me/messages', [
                'recipient' => ['comment_id' => $commentId],
                'message' => ['text' => $text],
            ])->throw()->json('message_id');
    }

    /**
     * A reply with a button: WhatsApp and Messenger text replies carry the link after the text.
     */
    public static function text(string $content, ?string $buttonLabel = null, ?string $buttonUrl = null): string
    {
        return filled($buttonLabel) && filled($buttonUrl) ? "{$content}\n\n{$buttonLabel}: {$buttonUrl}" : $content;
    }

    private function graph(Channel $channel): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.config('services.meta.graph_version').'/')
            ->withToken($channel->access_token)
            ->connectTimeout(5)
            ->timeout(15);
    }
}
