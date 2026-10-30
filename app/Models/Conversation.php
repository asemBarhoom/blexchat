<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'platform_conversation_id',
        'contact_name',
        'contact_avatar',
        'contact_platform_id',
        'status',
        'unread_count',
        'last_message',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'unread_count'    => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
