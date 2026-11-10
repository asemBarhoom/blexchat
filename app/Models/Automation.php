<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A rule that answers comments, DMs or story replies. The wizard (W11+) speaks in its own terms
 * — a kind, a post, words, a DM with a link — and `fromWizard` / `wizardAnswers` translate
 * between those and the trigger, keywords and actions the AutomationRunner reads.
 *
 * @property string $trigger_type
 * @property string|null $post_mode
 * @property int|null $post_id
 * @property array<string, mixed>|null $extras
 * @property Carbon|null $deleted_at
 */
class Automation extends Model
{
    use BelongsToWorkspace;
    use HasFactory, Prunable, SoftDeletes;

    /** How long a deleted automation stays in the Trash before it is deleted for good. */
    public const TRASH_DAYS = 30;

    /** The wizard's kinds, by the trigger_type each one is stored as. */
    public const KINDS = ['comment_keyword' => 'comment', 'story_mention' => 'story', 'dm_keyword' => 'dm'];

    public const POST_MODES = ['specific', 'any', 'next'];

    /** Post modes beyond one specific post need a paid plan. */
    public const PAID_POST_MODES = ['any', 'next'];

    /** The paid extras (W13, W14) and the answers each one keeps. */
    public const EXTRAS = [
        'ask_follow' => ['follow_text', 'follow_button'],
        'ask_email' => ['email_ask'],
        'follow_up' => ['follow_up_after', 'follow_up_text'],
    ];

    public const FOLLOW_UP_AFTER = ['1h', '6h', '24h'];

    /** How long each follow-up choice waits, in minutes. */
    private const FOLLOW_UP_MINUTES = ['1h' => 60, '6h' => 360, '24h' => 1440];

    protected $attributes = [
        'active' => true,
    ];

    protected $fillable = [
        'workspace_id',
        'user_id',
        'channel_id',
        'automation_folder_id',
        'automation_template_id',
        'name',
        'trigger_type',
        'post_mode',
        'post_id',
        'keywords',
        'actions',
        'extras',
        'active',
        'ai_enabled',
    ];

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'keywords' => 'array',
            'actions' => 'array',
            'extras' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Storage attributes for a set of wizard answers. A public reply is only kept for a comment,
     * and a post only for a comment watching one specific post.
     *
     * @param  array<string, mixed>  $answers  validated wizard answers, with `kind`
     * @return array<string, mixed>
     */
    public static function fromWizard(array $answers): array
    {
        $kind = $answers['kind'];
        $isComment = $kind === 'comment';
        $postMode = $isComment ? $answers['post_mode'] : null;
        $linkUrl = $answers['link_url'] ?? null;
        $actions = [];

        if ($isComment && ($answers['public_reply'] ?? false)) {
            $actions[] = ['type' => 'reply_comment', 'message' => $answers['public_reply_text']];
        }

        $actions[] = array_filter([
            'type' => filled($linkUrl) && filled($answers['dm_button'] ?? null) ? 'send_dm_with_button' : 'send_dm',
            'message' => $answers['dm_text'],
            'button_label' => $answers['dm_button'] ?? null,
            'button_url' => $linkUrl,
        ], fn (mixed $value): bool => filled($value));

        $extras = [];
        foreach (self::EXTRAS as $switch => $fields) {
            $extras[$switch] = (bool) ($answers[$switch] ?? false);
            foreach ($fields as $field) {
                if ($extras[$switch]) {
                    $extras[$field] = $answers[$field];
                }
            }
        }

        return [
            'name' => $answers['name'],
            'trigger_type' => array_search($kind, self::KINDS, true),
            'post_mode' => $postMode,
            'post_id' => $postMode === 'specific' ? $answers['post_id'] : null,
            'keywords' => $answers['trigger'] === 'words' ? array_values($answers['words']) : [],
            'actions' => $actions,
            'extras' => $extras,
        ];
    }

    /**
     * The automation as the wizard's answers, so an edit opens where it was left. Rules made
     * before the wizard read the same way: their first DM is the message.
     *
     * @return array<string, mixed>
     */
    public function wizardAnswers(): array
    {
        $actions = collect($this->actions ?? []);
        $publicReply = $actions->firstWhere('type', 'reply_comment');
        $dm = $actions->first(fn (array $action): bool => $action['type'] !== 'reply_comment');
        $keywords = $this->keywords ?? [];
        $extras = $this->extras ?? [];

        $answers = [
            'kind' => $this->kind(),
            'post_mode' => $this->kind() === 'comment' ? ($this->post_mode ?? 'any') : null,
            'post_id' => $this->post_id,
            'trigger' => $keywords === [] ? 'any' : 'words',
            'words' => array_values($keywords),
            'public_reply' => $publicReply !== null,
            'public_reply_text' => $publicReply['message'] ?? null,
            'dm_text' => $dm['message'] ?? '',
            'dm_button' => $dm['button_label'] ?? null,
            'link_url' => $dm['button_url'] ?? null,
        ];

        foreach (self::EXTRAS as $switch => $fields) {
            $answers[$switch] = (bool) ($extras[$switch] ?? false);
            foreach ($fields as $field) {
                $answers[$field] = $extras[$field] ?? null;
            }
        }

        return $answers;
    }

    /**
     * Whether a paid extra is on, with every answer it needs.
     */
    public function hasExtra(string $switch): bool
    {
        $extras = $this->extras ?? [];

        return ($extras[$switch] ?? false) === true
            && collect(self::EXTRAS[$switch] ?? [])->every(fn (string $field): bool => filled($extras[$field] ?? null));
    }

    /**
     * How long the follow-up waits after the DM, when it is on.
     */
    public function followUpDelayMinutes(): ?int
    {
        return $this->hasExtra('follow_up') ? (self::FOLLOW_UP_MINUTES[$this->extras['follow_up_after']] ?? null) : null;
    }

    /**
     * Moves the automation to the Trash, stopped: it answers nothing from then on, and restoring
     * it does not start it again.
     */
    public function moveToTrash(): void
    {
        // A soft delete only writes deleted_at, so the stop is saved on its own first.
        $this->update(['active' => false]);
        $this->delete();
    }

    /**
     * When a trashed automation is deleted for good.
     */
    public function purgesAt(): ?Carbon
    {
        return $this->deleted_at?->copy()->addDays(self::TRASH_DAYS);
    }

    /**
     * Trashed automations past their time in the Trash, for `model:prune`.
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::TRASH_DAYS));
    }

    /**
     * comment, story or dm.
     */
    public function kind(): ?string
    {
        return self::KINDS[$this->trigger_type] ?? null;
    }

    /**
     * Whether a comment on this post sets the automation off. "Next post" is the first post the
     * account published after the automation was made; rules made before the wizard watch every post.
     */
    public function watchesPost(Post $post): bool
    {
        return match ($this->post_mode) {
            'specific' => $this->post_id === $post->id,
            'next' => $post->is($this->nextPost()),
            default => true,
        };
    }

    private function nextPost(): ?Post
    {
        return Post::query()
            ->where('channel_id', $this->channel_id)
            ->where('posted_at', '>=', $this->created_at)
            ->orderBy('posted_at')
            ->orderBy('id')
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<AutomationFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(AutomationFolder::class, 'automation_folder_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AutomationTemplate::class, 'automation_template_id');
    }

    /**
     * The DMs it sent.
     *
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class)->where('automated', true);
    }

    /**
     * The comments it answered publicly.
     *
     * @return HasMany<Comment, $this>
     */
    public function publicReplies(): HasMany
    {
        return $this->hasMany(Comment::class)->where('automated_reply', true);
    }

    /**
     * The list's Runs and CTR, counted in the same query as the automations.
     *
     * @param  Builder<Automation>  $query
     */
    public function scopeWithListFigures(Builder $query): void
    {
        $query->withCount([
            'sentMessages as dm_sends',
            'sentMessages as button_sends' => fn (Builder $messages) => $messages->whereNotNull('link_token'),
            'sentMessages as button_clicks' => fn (Builder $messages) => $messages->whereNotNull('first_clicked_at'),
            'publicReplies as public_replies',
        ]);
    }

    public function hasListFigures(): bool
    {
        return array_key_exists('dm_sends', $this->getAttributes());
    }

    /**
     * Everything it sent: DMs and public comment replies.
     */
    public function runs(): int
    {
        return (int) $this->dm_sends + (int) $this->public_replies;
    }

    /**
     * Clicks as a share of the DMs that had a button; 0 while none had one.
     */
    public function clickThroughRate(): float
    {
        return $this->button_sends > 0 ? round($this->button_clicks / $this->button_sends, 4) : 0.0;
    }
}
