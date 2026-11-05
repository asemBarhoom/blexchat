<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'platform_message_id',
        'direction',
        'type',
        'content',
        'button_label',
        'button_url',
        'link_token',
        'automated',
        'automation_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'automated' => 'boolean',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
