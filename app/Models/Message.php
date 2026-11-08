<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    /** inbound and outbound are chat messages; a system line (a handover) was sent to nobody. */
    public const DIRECTION_SYSTEM = 'system';

    /** "Handed over to you": `content` holds the reason code (Conversation::HANDOVER_REASONS). */
    public const TYPE_HANDOVER = 'handover';

    /** The customer's comment that opened the conversation; `comment_id` names it. */
    public const TYPE_COMMENT = 'comment';

    protected $fillable = [
        'conversation_id',
        'comment_id',
        'platform_message_id',
        'direction',
        'type',
        'content',
        'note',
        'button_label',
        'button_url',
        'link_token',
        'automated',
        'by_ai',
        'automation_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'automated' => 'boolean',
            'by_ai' => 'boolean',
            'link_clicks' => 'integer',
            'sent_at' => 'datetime',
            'first_clicked_at' => 'datetime',
        ];
    }

    /**
     * The address a DM's button carries: it counts the click, then sends the customer to
     * button_url (LinkController).
     */
    public static function trackedUrl(string $token): string
    {
        return route('links.follow', $token);
    }

    /**
     * One more click on this message's button; the first one is what the click rate counts.
     */
    public function recordClick(): void
    {
        $this->increment('link_clicks', 1, ['first_clicked_at' => $this->first_clicked_at ?? now()]);
    }

    /**
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
