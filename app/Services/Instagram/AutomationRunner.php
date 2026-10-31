<?php

namespace App\Services\Instagram;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Runs the first matching automation for an incoming comment or message and sends its replies.
 * A failed send is logged and not retried: Instagram has no idempotency key, so a retry could reach
 * the customer twice.
 */
class AutomationRunner
{
    private const DM_ACTIONS = ['send_dm', 'send_dm_with_button'];

    public function __construct(private InstagramClient $instagram) {}

    /**
     * Comment automations answer publicly under the comment and/or privately in the commenter's DMs.
     */
    public function forComment(Channel $channel, Comment $comment): void
    {
        $automation = $this->firstMatch($channel, 'comment_keyword', $comment->text);

        if ($automation === null) {
            return;
        }

        $sent = false;

        foreach ($automation->actions as $action) {
            $sent = $this->attempt($automation, $action, function () use ($channel, $comment, $automation, $action): void {
                if ($action['type'] === 'reply_comment') {
                    $this->instagram->replyToComment($channel, $comment->platform_comment_id, $action['message']);

                    return;
                }

                $messageId = $this->instagram->sendPrivateReply($channel, $comment->platform_comment_id, $this->payload($action));
                $conversation = Conversation::firstOrCreate(
                    ['channel_id' => $channel->id, 'platform_conversation_id' => $comment->author_platform_id],
                    ['contact_platform_id' => $comment->author_platform_id, 'contact_name' => $comment->author_name, 'status' => 'open'],
                );
                $this->record($conversation, $automation, $action, $messageId);
            }) || $sent;
        }

        if ($sent) {
            $comment->update(['automated_reply' => true, 'automation_id' => $automation->id]);
        }
    }

    /**
     * DM and story-reply automations answer in the conversation. One without keywords answers
     * only the first message of a conversation, so it greets people instead of repeating itself.
     */
    public function forMessage(Channel $channel, Conversation $conversation, Message $message, bool $isStoryReply): void
    {
        $isFirstMessage = $conversation->messages()->where('direction', 'inbound')->count() === 1;
        $automation = $this->firstMatch($channel, $isStoryReply ? 'story_mention' : 'dm_keyword', $message->content, answerAny: $isStoryReply || $isFirstMessage);

        if ($automation === null) {
            return;
        }

        foreach ($automation->actions as $action) {
            if (! in_array($action['type'], self::DM_ACTIONS, true)) {
                continue;
            }

            $this->attempt($automation, $action, function () use ($channel, $conversation, $automation, $action): void {
                $messageId = $this->instagram->sendMessage($channel, $conversation->contact_platform_id, $this->payload($action));
                $this->record($conversation, $automation, $action, $messageId);
            });
        }
    }

    /**
     * Keywords match anywhere in the text, ignoring case; an automation without keywords matches
     * anything when $answerAny allows it.
     */
    private function firstMatch(Channel $channel, string $trigger, string $text, bool $answerAny = true): ?Automation
    {
        $text = mb_strtolower($text);

        return Automation::query()
            ->where('channel_id', $channel->id)
            ->where('trigger_type', $trigger)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->first(function (Automation $automation) use ($text, $answerAny): bool {
                $keywords = array_filter(array_map(fn (string $keyword): string => mb_strtolower(trim($keyword)), $automation->keywords ?? []));

                if ($keywords === []) {
                    return $answerAny;
                }

                return collect($keywords)->contains(fn (string $keyword): bool => str_contains($text, $keyword));
            });
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function attempt(Automation $automation, array $action, \Closure $send): bool
    {
        try {
            $send();

            return true;
        } catch (RequestException|ConnectionException $exception) {
            Log::warning('Instagram automation reply failed', [
                'automation_id' => $automation->id,
                'action' => $action['type'],
                'error' => $exception instanceof RequestException ? $exception->response->json('error.message') : $exception->getMessage(),
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
        return InstagramClient::message($action['message'], $action['button_label'] ?? null, $action['button_url'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function record(Conversation $conversation, Automation $automation, array $action, ?string $messageId): void
    {
        $withButton = filled($action['button_label'] ?? null) && filled($action['button_url'] ?? null);

        $conversation->messages()->create([
            'platform_message_id' => $messageId,
            'direction' => 'outbound',
            'type' => $withButton ? 'text_with_button' : 'text',
            'content' => $action['message'],
            'button_label' => $withButton ? $action['button_label'] : null,
            'button_url' => $withButton ? $action['button_url'] : null,
            'automated' => true,
            'automation_id' => $automation->id,
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message' => $action['message'], 'last_message_at' => now()]);
    }
}
