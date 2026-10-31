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
        'automated',
        'automation_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'automated' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
