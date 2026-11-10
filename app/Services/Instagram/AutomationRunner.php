<?php

namespace App\Services\Instagram;

use App\Jobs\SendAutomationFollowUp;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Services\Ai\ClaudeClient;
use App\Services\Channels\ChannelNotLive;
use App\Services\Channels\MessagingClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs the first matching automation for an incoming comment or message and sends its replies,
 * with its paid extras: "Follow us first" before the DM, the email question after it, and a
 * follow-up for someone who did not act on it. A failed send is logged and not retried:
 * Instagram has no idempotency key, so a retry could reach the customer twice.
 */
class AutomationRunner
{
    private const DM_ACTIONS = ['send_dm', 'send_dm_with_button'];

    /** The quick reply under "Follow us first". Any reply carries on; the chip makes it one tap. */
    private const FOLLOWED_PAYLOAD = 'BLEXCHAT_FOLLOWED';

    public function __construct(private InstagramClient $instagram, private ClaudeClient $claude, private MessagingClient $messaging) {}

    /**
     * Comment automations answer publicly under the comment and/or privately in the commenter's DMs.
     */
    public function forComment(Channel $channel, Comment $comment): void
    {
        $automation = $this->firstMatch($channel, 'comment_keyword', $comment->text, post: $comment->post);

        if ($automation === null) {
            return;
        }

        $sent = false;
        $asksToFollow = $automation->hasExtra('ask_follow');
        $askedToFollow = false;
        $mayOpenConversation = $this->acceptsNewConversations($channel)
            || Conversation::where('channel_id', $channel->id)->where('platform_conversation_id', $comment->author_platform_id)->exists();

        foreach ($automation->actions as $action) {
            $isDm = in_array($action['type'], self::DM_ACTIONS, true);

            // "Follow us first" takes the DMs' place; they wait for the reply to it.
            if ($isDm && (! $mayOpenConversation || $askedToFollow)) {
                continue;
            }

            $sent = $this->attempt($automation, $action, function () use ($channel, $comment, $automation, $action, $asksToFollow, &$askedToFollow): void {
                if ($action['type'] === 'reply_comment') {
                    if ($channel->platform === 'instagram') {
                        $this->instagram->replyToComment($channel, $comment->platform_comment_id, $action['message']);
                    } else {
                        $this->messaging->replyToComment($channel, $comment->platform_comment_id, $action['message']);
                    }

                    return;
                }

                $action = $asksToFollow ? $this->followAsk($automation) : $this->withLinkToken($action);
                $messageId = $this->instagram->sendPrivateReply($channel, $comment->platform_comment_id, $this->payload($action));
                $conversation = Conversation::firstOrCreate(
                    ['channel_id' => $channel->id, 'platform_conversation_id' => $comment->author_platform_id],
                    ['contact_platform_id' => $comment->author_platform_id, 'contact_name' => $comment->author_name, 'status' => 'open'],
                );
                $this->recordComment($conversation, $comment);
                $dm = $this->record($conversation, $automation, $action, $messageId);

                if ($asksToFollow) {
                    $askedToFollow = true;
                    $conversation->awaitReply('follow', $automation);

                    return;
                }

                // After a private reply, Instagram allows nothing more until they write back.
                $this->afterDm($conversation, $automation, $dm, canMessageNow: false);
            }) || $sent;
        }

        if ($sent) {
            $comment->update(['automated_reply' => true, 'automation_id' => $automation->id]);
        }
    }

    /**
     * DM and story-reply automations answer in the conversation. One without keywords answers
     * only the first message of a conversation, so it greets people instead of repeating itself.
     * A step an automation is waiting on comes first.
     */
    public function forMessage(Channel $channel, Conversation $conversation, Message $message, bool $isStoryReply): void
    {
        if ($this->carryOnAwaited($channel, $conversation, $message)) {
            return;
        }

        $isFirstMessage = $conversation->messages()->where('direction', 'inbound')->count() === 1;

        if ($isFirstMessage && $conversation->wasRecentlyCreated && ! $this->acceptsNewConversations($channel)) {
            return;
        }

        $automation = $this->firstMatch($channel, $isStoryReply ? 'story_mention' : 'dm_keyword', $message->content, answerAny: $isStoryReply || $isFirstMessage);

        if ($automation === null) {
            // Nothing answers this, and an automation had been: Blexchat AI tries, else the owner takes it from here.
            if ($conversation->state === 'automation' && ! $this->answerWithAi($conversation)) {
                $conversation->handOver('unmatched');
            }

            return;
        }

        $this->sendDms($channel, $conversation, $automation, mayAskToFollow: true);
    }

    /**
     * Blexchat AI answers for the automation that was handling the conversation, when that
     * automation has it on, the plan includes it and the owner wrote the business info. Returns
     * false when it did not run, so the conversation is handed over as usual.
     */
    private function answerWithAi(Conversation $conversation): bool
    {
        $automation = Automation::find($conversation->messages()->where('automated', true)->latest('id')->value('automation_id'));
        $owner = $conversation->channel->user;

        if (! $automation?->ai_enabled || ! $owner->currentPlan()->has_ai || blank($owner->business_info) || ! $this->claude->available()) {
            return false;
        }

        $history = $conversation->messages()
            ->whereIn('direction', ['inbound', 'outbound'])
            ->latest('id')
            ->limit(20)
            ->get(['direction', 'content'])
            ->reverse()
            ->map(fn (Message $message): array => ['from' => $message->direction === 'inbound' ? 'customer' : 'business', 'text' => (string) $message->content])
            ->values()
            ->all();

        try {
            $answer = $this->claude->answer($owner->business_name ?: $owner->name, $owner->business_info, $history, $owner->preferredLocale() === 'ar' ? 'Arabic' : 'English');
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            return false;
        }

        if ($answer['handover']) {
            $conversation->handOver('ai_unsure', $answer['reason']);

            return true;
        }

        $action = ['type' => 'send_dm', 'message' => $answer['reply']];

        return $this->attempt($automation, $action, function () use ($conversation, $automation, $action): void {
            $messageId = $this->sendMessage($conversation->channel, $conversation->contact_platform_id, $action);
            $this->record($conversation, $automation, $action, $messageId)->update(['by_ai' => true]);
        });
    }

    /**
     * The automation's follow-up, sent by SendAutomationFollowUp once its wait is over: only to
     * someone who neither tapped the DM's button nor wrote back, while the automation still runs.
     */
    public function followUp(Message $dm): void
    {
        $automation = $dm->automation;
        $conversation = $dm->conversation;

        if ($automation === null || ! $automation->active || ! $automation->hasExtra('follow_up') || $dm->first_clicked_at !== null) {
            return;
        }

        $text = $automation->extras['follow_up_text'];
        $since = $conversation->messages()->where('id', '>', $dm->id);

        if ((clone $since)->where('direction', 'inbound')->exists() || (clone $since)->where('automated', true)->where('content', $text)->exists()) {
            return;
        }

        $this->sendText($conversation, $automation, ['type' => 'send_dm', 'message' => $text]);
    }

    /**
     * The inbox's "Accept new conversations" switch: while it is off, automations leave people
     * who have no conversation yet unanswered in DMs. Their messages still reach the inbox.
     */
    private function acceptsNewConversations(Channel $channel): bool
    {
        return (bool) $channel->user->inbox_accepting_new;
    }

    /**
     * Keywords match anywhere in the text, ignoring case; an automation without keywords matches
     * anything when $answerAny allows it. A comment only matches automations watching its post.
     */
    private function firstMatch(Channel $channel, string $trigger, string $text, bool $answerAny = true, ?Post $post = null): ?Automation
    {
        $text = mb_strtolower($text);

        return Automation::query()
            ->where('channel_id', $channel->id)
            ->where('trigger_type', $trigger)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->first(function (Automation $automation) use ($text, $answerAny, $post): bool {
                if ($post !== null && ! $automation->watchesPost($post)) {
                    return false;
                }

                $keywords = array_filter(array_map(fn (string $keyword): string => mb_strtolower(trim($keyword)), $automation->keywords ?? []));

                if ($keywords === []) {
                    return $answerAny;
                }

                return collect($keywords)->contains(fn (string $keyword): bool => str_contains($text, $keyword));
            });
    }

    /**
     * The DM actions in a conversation they wrote in, after "Follow us first" when that is on.
     */
    private function sendDms(Channel $channel, Conversation $conversation, Automation $automation, bool $mayAskToFollow): void
    {
        if ($mayAskToFollow && $automation->hasExtra('ask_follow')) {
            if ($this->sendText($conversation, $automation, $this->followAsk($automation))) {
                $conversation->awaitReply('follow', $automation);
            }

            return;
        }

        $dm = null;

        foreach ($automation->actions as $action) {
            if (! in_array($action['type'], self::DM_ACTIONS, true)) {
                continue;
            }

            $this->attempt($automation, $action, function () use ($channel, $conversation, $automation, $action, &$dm): void {
                $action = $this->withLinkToken($action);
                $messageId = $this->sendMessage($channel, $conversation->contact_platform_id, $action);
                $dm = $this->record($conversation, $automation, $action, $messageId);
            });
        }

        if ($dm !== null) {
            $this->afterDm($conversation, $automation, $dm, canMessageNow: true);
        }
    }

    /**
     * What follows the DM: the follow-up's wait starts, and the email question goes out now, or
     * with their first reply when Instagram does not allow another message yet.
     */
    private function afterDm(Conversation $conversation, Automation $automation, Message $dm, bool $canMessageNow): void
    {
        $followUpMinutes = $automation->followUpDelayMinutes();

        if ($followUpMinutes !== null) {
            SendAutomationFollowUp::dispatch($dm)->delay(now()->addMinutes($followUpMinutes));
        }

        if (! $automation->hasExtra('ask_email')) {
            return;
        }

        if (! $canMessageNow) {
            $conversation->awaitReply('email_question', $automation);

            return;
        }

        $this->askForEmail($conversation, $automation);
    }

    private function askForEmail(Conversation $conversation, Automation $automation): void
    {
        if ($this->sendText($conversation, $automation, ['type' => 'send_dm', 'message' => $automation->extras['email_ask']])) {
            $conversation->awaitReply('email', $automation);
        }
    }

    /**
     * Their message carries on the step an automation was waiting on: a reply to "Follow us first"
     * gets the DM, a first reply after a comment gets the email question, and an email address is
     * saved on the contact. Anything else, or a stopped automation, goes to the usual matching.
     */
    private function carryOnAwaited(Channel $channel, Conversation $conversation, Message $message): bool
    {
        $step = $conversation->awaiting;

        if ($step === null) {
            return false;
        }

        $automation = $conversation->awaitingAutomation;
        $conversation->stopAwaiting();

        if ($automation === null || ! $automation->active) {
            return false;
        }

        if ($step === 'follow') {
            $this->sendDms($channel, $conversation, $automation, mayAskToFollow: false);

            return true;
        }

        if ($step === 'email_question') {
            $this->askForEmail($conversation, $automation);

            return true;
        }

        $email = $this->emailIn($message->content);

        if ($email === null) {
            return false;
        }

        $conversation->contact?->update(['email' => $email]);

        return true;
    }

    /**
     * The first email address in what they wrote, lower-cased.
     */
    private function emailIn(string $text): ?string
    {
        if (preg_match('/[^\s@<>()",;:]+@[^\s@<>()",;:]+\.[^\s@<>()",;:]+/u', $text, $match) !== 1) {
            return null;
        }

        $email = mb_strtolower(rtrim($match[0], '.'));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * "Follow us first", with the owner's button as a quick reply.
     *
     * @return array<string, mixed>
     */
    private function followAsk(Automation $automation): array
    {
        return ['type' => 'send_dm', 'message' => $automation->extras['follow_text'], 'quick_reply' => $automation->extras['follow_button']];
    }

    /**
     * One of the automation's messages in a conversation they wrote in, recorded once it went out.
     *
     * @param  array<string, mixed>  $action
     */
    private function sendText(Conversation $conversation, Automation $automation, array $action): bool
    {
        return $this->attempt($automation, $action, function () use ($conversation, $automation, $action): void {
            $messageId = $this->sendMessage($conversation->channel, $conversation->contact_platform_id, $action);
            $this->record($conversation, $automation, $action, $messageId);
        });
    }

    /**
     * Instagram takes the message as a payload (button template, quick reply). WhatsApp, Messenger
     * and TikTok take plain text, with the button's link after the text; a quick reply chip has no
     * text equivalent, and any reply carries on anyway.
     *
     * @param  array<string, mixed>  $action
     */
    private function sendMessage(Channel $channel, string $recipientId, array $action): ?string
    {
        if (! in_array($channel->platform, MessagingClient::PLATFORMS, true)) {
            return $this->instagram->sendMessage($channel, $recipientId, $this->payload($action));
        }

        $url = isset($action['link_token']) ? Message::trackedUrl($action['link_token']) : ($action['button_url'] ?? null);

        return $this->messaging->send($channel, $recipientId, MessagingClient::text($action['message'], $action['button_label'] ?? null, $url));
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function attempt(Automation $automation, array $action, \Closure $send): bool
    {
        try {
            $send();

            return true;
        } catch (RequestException|ConnectionException|ChannelNotLive $exception) {
            Log::warning('Automation reply failed', [
                'automation_id' => $automation->id,
                'action' => $action['type'],
                'error' => $exception instanceof RequestException ? InstagramClient::errorMessage($exception) : $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function payload(array $action): array
    {
        if (filled($action['quick_reply'] ?? null)) {
            return InstagramClient::withQuickReply($action['message'], $action['quick_reply'], self::FOLLOWED_PAYLOAD);
        }

        $url = isset($action['link_token']) ? Message::trackedUrl($action['link_token']) : ($action['button_url'] ?? null);

        return InstagramClient::message($action['message'], $action['button_label'] ?? null, $url);
    }

    /**
     * A button's link goes out through /l/{token}, so the automation's page can count the clicks.
     *
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function withLinkToken(array $action): array
    {
        if (blank($action['button_label'] ?? null) || blank($action['button_url'] ?? null)) {
            return $action;
        }

        return [...$action, 'link_token' => Str::random(32)];
    }

    /**
     * The thread opens with what they commented, so the DM that follows reads as an answer.
     * Their comment is not counted as unread: it is already on the post.
     */
    private function recordComment(Conversation $conversation, Comment $comment): void
    {
        $conversation->messages()->firstOrCreate(['comment_id' => $comment->id], [
            'direction' => 'inbound',
            'type' => Message::TYPE_COMMENT,
            'content' => $comment->text,
            'automated' => false,
            'sent_at' => $comment->posted_at ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function record(Conversation $conversation, Automation $automation, array $action, ?string $messageId): Message
    {
        $withButton = filled($action['button_label'] ?? null) && filled($action['button_url'] ?? null);

        $message = $conversation->messages()->create([
            'platform_message_id' => $messageId,
            'direction' => 'outbound',
            'type' => $withButton ? 'text_with_button' : 'text',
            'content' => $action['message'],
            'button_label' => $withButton ? $action['button_label'] : null,
            'button_url' => $withButton ? $action['button_url'] : null,
            'link_token' => $withButton ? ($action['link_token'] ?? null) : null,
            'automated' => true,
            'automation_id' => $automation->id,
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message' => $action['message'], 'last_message_at' => now()]);
        $conversation->markAnsweredByAutomation();

        return $message;
    }
}
