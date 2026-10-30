<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'channel_id',
        'platform_comment_id',
        'author_name',
        'author_platform_id',
        'text',
        'automated_reply',
        'automation_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'automated_reply' => 'boolean',
            'posted_at'       => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
