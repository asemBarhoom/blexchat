<?php

namespace App\Services\Instagram;

use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

/**
 * Turns Instagram webhook events into posts, comments, conversations and messages.
 * Every event is keyed by Instagram's own id, so a redelivered event changes nothing.
 */
class InstagramEventHandler
{
    public function __construct(private InstagramClient $instagram, private AutomationRunner $automations) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public function comment(Channel $channel, array $value): void
    {
        $authorId = (string) data_get($value, 'from.id');

        // The account's own comments include the replies our automations post.
        if ($authorId === '' || $authorId === $channel->account_id || empty($value['id'])) {
            return;
        }

        $comment = Comment::firstOrCreate(
            ['channel_id' => $channel->id, 'platform_comment_id' => (string) $value['id']],
            [
                'post_id' => $this->post($channel, (string) data_get($value, 'media.id'), data_get($value, 'media.media_product_type'))->id,
                'author_name' => data_get($value, 'from.username'),
                'author_platform_id' => $authorId,
                'text' => (string) ($value['text'] ?? ''),
                'posted_at' => now(),
            ],
        );

        if ($comment->wasRecentlyCreated) {
            $this->automations->forComment($channel, $comment);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function message(Channel $channel, array $event): void
    {
        $payload = $event['message'] ?? null;
        $senderId = (string) data_get($event, 'sender.id');

        // Echoes are the messages this account sent, already stored when they were sent.
        if (! is_array($payload) || empty($payload['mid']) || ! empty($payload['is_echo']) || ! empty($payload['is_deleted']) || $senderId === '') {
            return;
        }

        $sentAt = isset($event['timestamp']) ? Carbon::createFromTimestampMs($event['timestamp']) : now();
        $text = $payload['text'] ?? '['.data_get($payload, 'attachments.0.type', 'attachment').']';
        $conversation = $this->conversation($channel, $senderId);

        $message = Message::firstOrCreate(['platform_message_id' => (string) $payload['mid']], [
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'content' => $text,
            'automated' => false,
            'sent_at' => $sentAt,
        ]);

        if (! $message->wasRecentlyCreated) {
            return;
        }

        $conversation->update(['status' => 'open', 'last_message' => $text, 'last_message_at' => $sentAt]);
        $conversation->increment('unread_count');

        $this->automations->forMessage($channel, $conversation, $message, isStoryReply: isset($payload['reply_to']['story']));
    }

    private function conversation(Channel $channel, string $contactId): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['channel_id' => $channel->id, 'platform_conversation_id' => $contactId],
            ['contact_platform_id' => $contactId, 'status' => 'open'],
        );

        if ($conversation->wasRecentlyCreated) {
            try {
                $contact = $this->instagram->contact($channel, $contactId);
                $conversation->update(['contact_name' => $contact['username'] ?? $contact['name'] ?? null, 'contact_avatar' => $contact['profile_pic'] ?? null]);
            } catch (RequestException|ConnectionException) {
                // The conversation works without a name; Instagram only shares it for some accounts.
            }
        }

        return $conversation;
    }

    private function post(Channel $channel, string $mediaId, ?string $productType): Post
    {
        $post = Post::firstOrCreate(['channel_id' => $channel->id, 'platform_post_id' => $mediaId]);

        if ($post->wasRecentlyCreated) {
            try {
                $media = $this->instagram->media($channel, $mediaId);
                $post->update(Post::attributesFromInstagram([...$media, 'media_product_type' => $productType ?? $media['media_product_type'] ?? null]));
            } catch (RequestException|ConnectionException) {
                // The comment is still handled; the post shows without its caption.
            }
        }

        return $post;
    }
}
