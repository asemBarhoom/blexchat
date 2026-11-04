<?php

namespace App\Services\Instagram;

use App\Models\Channel;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

/**
 * Turns Instagram webhook events into posts, comments, conversations, messages and contacts.
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

        if (! $comment->wasRecentlyCreated) {
            return;
        }

        $contact = Contact::recordActivity($channel, $authorId, handle: $comment->author_name);
        $this->automations->forComment($channel, $comment);

        // The private reply may have opened their conversation.
        Conversation::where('channel_id', $channel->id)->where('contact_platform_id', $authorId)->whereNull('contact_id')->update(['contact_id' => $contact->id]);
        $this->creditAutomation($contact, $comment->refresh()->automation_id);
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
        $this->creditAutomation($conversation->contact, $conversation->messages()->where('automated', true)->value('automation_id'));
    }

    /**
     * The automation that answered someone first is what brought them in: the contacts list's Source.
     */
    private function creditAutomation(?Contact $contact, ?int $automationId): void
    {
        if ($contact !== null && $automationId !== null && $contact->wasRecentlyCreated) {
            $contact->update(['source_automation_id' => $automationId]);
        }
    }

    private function conversation(Channel $channel, string $contactId): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['channel_id' => $channel->id, 'platform_conversation_id' => $contactId],
            ['contact_platform_id' => $contactId, 'status' => 'open'],
        );

        $profile = [];

        if ($conversation->wasRecentlyCreated) {
            try {
                $profile = $this->instagram->contact($channel, $contactId);
                $conversation->update(['contact_name' => $profile['username'] ?? $profile['name'] ?? null, 'contact_avatar' => $profile['profile_pic'] ?? null]);
            } catch (RequestException|ConnectionException) {
                // The conversation works without a name; Instagram only shares it for some accounts.
            }
        }

        $contact = Contact::recordActivity($channel, $contactId, $profile['name'] ?? null, $profile['username'] ?? $conversation->contact_name, $profile['profile_pic'] ?? $conversation->contact_avatar);
        $conversation->contact()->associate($contact)->save();

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
