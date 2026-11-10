<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TeamInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Settings · Team: someone the owner invited to their inbox. The emailed link carries the token;
 * only its hash is kept. Accepting makes the signed-in user, with the same email, a teammate.
 *
 * @property string $email
 * @property Carbon $expires_at
 */
#[Fillable(['workspace_id', 'email', 'token_hash', 'expires_at'])]
#[Hidden(['token_hash'])]
class TeamInvitation extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    public const DAYS_VALID = 7;

    protected static function workspaceOwnerKey(): string
    {
        return 'owner_id';
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * A new invitation, or a fresh token and expiry for one sent before, with the plain token.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(User $owner, string $email): array
    {
        $token = Str::random(48);
        $invitation = $owner->teamInvitations()->updateOrCreate(['workspace_id' => $owner->workspace()->id, 'email' => Str::lower($email)], [
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::DAYS_VALID),
        ]);

        return [$invitation, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
    }
}
