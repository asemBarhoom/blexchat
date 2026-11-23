<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'platform',
        'account_id',
        'meta_user_id',
        'username',
        'profile_picture',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'followers',
        'status',
        'connected_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'followers' => 'integer',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * Whether the owner must connect the account again: the platform refused its token, or the token ran out.
     */
    public function needsReconnect(): bool
    {
        return $this->status === 'expired' || ($this->token_expires_at?->isPast() ?? false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
