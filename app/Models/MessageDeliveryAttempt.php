<?php

namespace App\Models;

use Database\Factories\MessageDeliveryAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDeliveryAttempt extends Model
{
    /** @use HasFactory<MessageDeliveryAttemptFactory> */
    use HasFactory;

    protected $fillable = ['conversation_id', 'idempotency_key', 'content', 'type', 'status', 'error_code', 'error_message', 'attempts', 'last_attempt_at'];

    protected function casts(): array
    {
        return ['last_attempt_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
