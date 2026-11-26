<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Services\Channels\ChannelEventHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * One TikTok for Business webhook event, for the account it names (`user_openid`): a person's
 * direct message goes to the inbox and its automations, and a new comment to the comment ones.
 * Other events (our own messages echoed back, read receipts, replies to comments) change nothing.
 */
class ProcessTikTokWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  the webhook body: client_key, event, create_time, user_openid, content
     */
    public function __construct(public array $payload) {}

    public function handle(ChannelEventHandler $handler): void
    {
        $channel = Channel::query()
            ->where('platform', 'tiktok')
            ->where('account_id', (string) ($this->payload['user_openid'] ?? ''))
            ->where('status', 'active')
            ->first();
        // The ids in `content` are 19-digit numbers: kept as strings so none is rounded.
        $content = json_decode((string) ($this->payload['content'] ?? ''), true, 512, JSON_BIGINT_AS_STRING);

        if ($channel === null || ! is_array($content)) {
            return;
        }

        match ($this->payload['event'] ?? null) {
            'im_receive_msg' => $this->message($handler, $channel, $content),
            'comment.update' => $this->comment($handler, $channel, $content),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function message(ChannelEventHandler $handler, Channel $channel, array $content): void
    {
        // Only a person's message is new: the account's own come back as business_account.
        if (data_get($content, 'from_user.role') !== 'personal_account') {
            return;
        }

        $handler->message(
            $channel,
            (string) data_get($content, 'from_user.id', ''),
            (string) ($content['message_id'] ?? ''),
            data_get($content, 'text.body') ?? '['.($content['type'] ?? 'attachment').']',
            isset($content['from']) ? (string) $content['from'] : null,
            sentAt: isset($content['timestamp']) ? Carbon::createFromTimestampMs((int) $content['timestamp']) : null,
            conversationId: (string) ($content['conversation_id'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function comment(ChannelEventHandler $handler, Channel $channel, array $content): void
    {
        // Only a new top-level comment: replies include the answers our automations post.
        if (($content['comment_action'] ?? null) !== 'insert' || ($content['comment_type'] ?? null) !== 'comment') {
            return;
        }

        $handler->comment(
            $channel,
            (string) ($content['comment_id'] ?? ''),
            (string) ($content['unique_identifier'] ?? ''),
            (string) ($content['text'] ?? ''),
            postId: isset($content['video_id']) ? (string) $content['video_id'] : null,
        );
    }
}
