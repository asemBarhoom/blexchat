<?php

namespace App\Models;

use Database\Factories\ChannelAccessRequestFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * "Request access" on W06: `user` signed in to an account that another workspace already connected
 * (`channel`) and asks for access to it. `decided_at` follows the status: set when it is approved or
 * declined, cleared when it is pending again.
 *
 * @property string $platform
 * @property string $account_id
 * @property string|null $account_handle
 * @property string $status
 * @property Carbon|null $decided_at
 */
#[Fillable(['user_id', 'channel_id', 'platform', 'account_id', 'account_handle', 'status', 'note'])]
class ChannelAccessRequest extends Model
{
    /** @use HasFactory<ChannelAccessRequestFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'declined'];

    /** How long after a refused connection the owner can still ask for access. */
    public const TOKEN_MINUTES = 60;

    protected $attributes = ['status' => 'pending'];

    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            if ($request->isDirty('status')) {
                $request->decided_at = $request->isPending() ? null : now();
            }
        });
    }

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /**
     * Proof, handed out with an `account_taken` refusal, that `$user` signed in to the account behind
     * `$channel`: asking for access needs it, so nobody can ask for an account they never signed in to.
     */
    public static function tokenFor(User $user, Channel $channel): string
    {
        return Crypt::encryptString(json_encode([
            'user' => $user->id,
            'channel' => $channel->id,
            'expires' => now()->addMinutes(self::TOKEN_MINUTES)->timestamp,
        ]));
    }

    /**
     * The channel a token from {@see tokenFor()} names, when it was issued to `$user`, is still fresh,
     * and the channel is still another workspace's.
     */
    public static function channelFromToken(string $token, User $user): ?Channel
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if ((int) ($payload['user'] ?? 0) !== $user->id || (int) ($payload['expires'] ?? 0) < now()->timestamp) {
            return null;
        }

        $channel = Channel::find((int) ($payload['channel'] ?? 0));

        return $channel !== null && $channel->user_id !== $user->id ? $channel : null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', 'pending');
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
