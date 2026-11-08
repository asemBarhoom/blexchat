<?php

namespace App\Services\Channels;

use App\Models\Channel;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Services\Instagram\AutomationRunner;
use Illuminate\Support\Carbon;

/**
 * An incoming WhatsApp, Messenger or TikTok message: into the inbox as a conversation, a message
 * and a contact. Keyed by the platform's message id, so a redelivered event changes nothing.
 * Automations answer on Instagram only for now; these wait for the owner.
 */
class ChannelEventHandler
{
    public function __construct(private AutomationRunner $automations) {}

    /**
     * @param  string|null  $phone  WhatsApp's sender number, saved on the contact
     */
    public function message(Channel $channel, string $senderId, string $messageId, string $text, ?string $senderName = null, ?string $phone = null, ?Carbon $sentAt = null): void
    {
        if ($senderId === '' || $messageId === '') {
            return;
        }

        $sentAt ??= now();
        $conversation = Conversation::firstOrCreate(
            ['channel_id' => $channel->id, 'platform_conversation_id' => $senderId],
            ['contact_platform_id' => $senderId, 'contact_name' => $senderName, 'status' => 'open'],
        );

        $message = Message::firstOrCreate(['platform_message_id' => $messageId], [
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

        $contact = Contact::recordActivity($channel, $senderId, $senderName);

        if ($phone !== null && $contact->phone === null) {
            $contact->update(['phone' => $phone]);
        }

        $conversation->contact()->associate($contact);
        $conversation->fill(['status' => 'open', 'last_message' => $text, 'last_message_at' => $sentAt, 'contact_name' => $conversation->contact_name ?? $senderName])->save();
        $conversation->increment('unread_count');
        $this->automations->forMessage($channel, $conversation, $message, isStoryReply: false);
    }

    public function comment(Channel $channel, string $commentId, string $senderId, string $text, ?string $senderName = null, ?string $postId = null): void
    {
        if ($commentId === '' || $senderId === '') {
            return;
        }

        $post = Post::firstOrCreate(['channel_id' => $channel->id, 'platform_post_id' => $postId ?: 'fake-post']);
        $comment = Comment::firstOrCreate(['channel_id' => $channel->id, 'platform_comment_id' => $commentId], [
            'post_id' => $post->id,
            'author_name' => $senderName,
            'author_platform_id' => $senderId,
            'text' => $text,
            'posted_at' => now(),
        ]);

        if ($comment->wasRecentlyCreated) {
            $this->automations->forComment($channel, $comment);
        }
    }
}
