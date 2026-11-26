<?php

namespace App\Services\Channels;

use App\Models\Channel;
use App\Services\TikTok\TikTokClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Sends a reply on WhatsApp (Cloud API), Messenger (Send API) or TikTok (TikTok for Business), over
 * the channel's own token. Instagram has InstagramClient. Sends are never retried, so nobody gets a
 * reply twice. MESSAGING_DRIVER=fake (the default on local) logs instead of sending.
 */
class MessagingClient
{
    public const PLATFORMS = ['whatsapp', 'messenger', 'tiktok'];

    /** Messenger's button template: its text and button title can be no longer than this. */
    private const MESSENGER_TEMPLATE_TEXT_MAX = 640;

    private const MESSENGER_BUTTON_TITLE_MAX = 20;

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
            // TikTok answers a conversation: the recipient is its conversation_id.
            'tiktok' => app(TikTokClient::class)->sendText($channel, $recipientId, $text),
            default => throw new ChannelNotLive($channel->platform),
        };
    }

    /**
     * A reply with a link button. Messenger shows a real button (its button template); WhatsApp,
     * TikTok and a Messenger text too long for the template get the link after the text instead.
     *
     * @throws RequestException|ConnectionException|ChannelNotLive
     */
    public function sendWithButton(Channel $channel, string $recipientId, string $text, ?string $buttonLabel, ?string $buttonUrl): ?string
    {
        if ($channel->platform !== 'messenger') {
            return $this->send($channel, $recipientId, self::text($text, $buttonLabel, $buttonUrl));
        }

        return $this->graph($channel)
            ->post('me/messages', [
                'recipient' => ['id' => $recipientId],
                'messaging_type' => 'RESPONSE',
                'message' => $this->messengerMessage($text, $buttonLabel, $buttonUrl),
            ])->throw()->json('message_id');
    }

    /**
     * A public reply under a comment on a Facebook Page or a TikTok post (which needs the post's id).
     *
     * @throws RequestException|ConnectionException|ChannelNotLive
     */
    public function replyToComment(Channel $channel, string $commentId, string $text, ?string $postId = null): ?string
    {
        return match ($channel->platform) {
            'messenger' => $this->graph($channel)->post("{$commentId}/comments", ['message' => $text])->throw()->json('id'),
            'tiktok' => app(TikTokClient::class)->replyToComment($channel, (string) $postId, $commentId, $text),
            default => throw new ChannelNotLive($channel->platform),
        };
    }

    /**
     * A private message to whoever wrote a comment on the Page, sent through Messenger, with its
     * link as a real button when it has one.
     *
     * @throws RequestException|ConnectionException|ChannelNotLive
     */
    public function sendPrivateReply(Channel $channel, string $commentId, string $text, ?string $buttonLabel = null, ?string $buttonUrl = null): ?string
    {
        if ($channel->platform !== 'messenger') {
            throw new ChannelNotLive($channel->platform);
        }

        return $this->graph($channel)
            ->post('me/messages', [
                'recipient' => ['comment_id' => $commentId],
                'message' => $this->messengerMessage($text, $buttonLabel, $buttonUrl),
            ])->throw()->json('message_id');
    }

    /**
     * A Messenger sender's name and picture, as Meta shares them with the Page they wrote to.
     *
     * @return array{name?: string, profile_pic?: string}
     *
     * @throws RequestException|ConnectionException
     */
    public function contact(Channel $channel, string $senderId): array
    {
        if ($channel->platform !== 'messenger') {
            return [];
        }

        return $this->graph($channel)->get($senderId, ['fields' => 'name,profile_pic'])->throw()->json() ?? [];
    }

    /**
     * The Facebook Page's latest posts, newest first, for "Pick a post".
     *
     * @return list<array{id: string, message?: string, full_picture?: string, permalink_url?: string, status_type?: string, created_time?: string, reactions?: array<string, mixed>, comments?: array<string, mixed>}>
     *
     * @throws RequestException|ConnectionException
     */
    public function recentPosts(Channel $channel): array
    {
        if ($channel->platform !== 'messenger') {
            return [];
        }

        return $this->graph($channel)->get("{$channel->account_id}/posts", [
            'fields' => 'id,message,full_picture,permalink_url,status_type,created_time,reactions.summary(true).limit(0),comments.summary(true).limit(0)',
            'limit' => 50,
        ])->throw()->json('data') ?? [];
    }

    /**
     * A reply with a button: WhatsApp and Messenger text replies carry the link after the text.
     */
    public static function text(string $content, ?string $buttonLabel = null, ?string $buttonUrl = null): string
    {
        return filled($buttonLabel) && filled($buttonUrl) ? "{$content}\n\n{$buttonLabel}: {$buttonUrl}" : $content;
    }

    /**
     * Messenger's message: its button template when there is a button and the text fits it, else
     * the text with the link after it.
     *
     * @return array<string, mixed>
     */
    private function messengerMessage(string $text, ?string $buttonLabel, ?string $buttonUrl): array
    {
        if (blank($buttonLabel) || blank($buttonUrl) || mb_strlen($text) > self::MESSENGER_TEMPLATE_TEXT_MAX) {
            return ['text' => self::text($text, $buttonLabel, $buttonUrl)];
        }

        return ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'button',
            'text' => $text,
            'buttons' => [['type' => 'web_url', 'url' => $buttonUrl, 'title' => mb_substr($buttonLabel, 0, self::MESSENGER_BUTTON_TITLE_MAX)]],
        ]]];
    }

    private function graph(Channel $channel): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.config('services.meta.graph_version').'/')
            ->withToken($channel->access_token)
            ->connectTimeout(5)
            ->timeout(15);
    }
}
