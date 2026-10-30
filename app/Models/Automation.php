<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Automation extends Model
{
    use HasFactory;

    protected $attributes = [
        'active' => true,
    ];

    protected $fillable = [
        'user_id',
        'channel_id',
        'name',
        'trigger_type',
        'keywords',
        'actions',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'actions' => 'array',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
