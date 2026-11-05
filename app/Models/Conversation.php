<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    /** Open, or resolved by the inbox's "Mark done". A new message reopens it. */
    public const STATUSES = ['open', 'resolved'];

    /**
     * Who the conversation waits on: nobody yet (open), an automation that is answering it,
     * or the owner (human, the inbox's "Needs you"), after an automation handed it over.
     */
    public const STATES = ['open', 'automation', 'human'];

    /** The inbox's folders besides All chats: starred conversations, and those with any label. */
    public const FOLDERS = ['favorites', 'labels'];

    public const MAX_LABELS = 10;

    /** What the inbox list shows with each conversation. */
    public const LIST_RELATIONS = ['channel', 'contact.sourceAutomation'];

    /** Why an automation hands a conversation over; each has a text in lang/{locale}/inbox.php. */
    public const HANDOVER_REASONS = ['unmatched'];

    protected $fillable = [
        'channel_id',
        'contact_id',
        'platform_conversation_id',
        'contact_name',
        'contact_avatar',
        'contact_platform_id',
        'status',
        'state',
        'starred',
        'labels',
        'unread_count',
        'last_message',
        'last_message_at',
    ];

    /** The column's default, so a conversation created a moment ago reads it too. */
    protected $attributes = [
        'state' => 'open',
        'starred' => false,
    ];

    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
            'starred' => 'boolean',
            'labels' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * An automation was answering, and the customer wrote something none answers: the thread
     * gets a "Handed over to you" line and the conversation moves to Needs you.
     */
    public function handOver(string $reason): void
    {
        if ($this->state === 'human') {
            return;
        }

        $this->messages()->create([
            'direction' => Message::DIRECTION_SYSTEM,
            'type' => Message::TYPE_HANDOVER,
            'content' => $reason,
            'automated' => false,
            'sent_at' => now(),
        ]);

        $this->update(['state' => 'human']);
    }

    /**
     * An automation answered; a conversation already waiting on the owner stays in Needs you.
     */
    public function markAnsweredByAutomation(): void
    {
        if ($this->state === 'open') {
            $this->update(['state' => 'automation']);
        }
    }

    /**
     * The owner replied or marked it done: nobody is waiting any more.
     */
    public function markHandledByOwner(): void
    {
        $this->update(['state' => 'open']);
    }
}
