<?php

namespace App\Models;

use Database\Factories\LoginLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A sign-in link emailed to an address. It works once, for LIFETIME_MINUTES.
 *
 * @property string $email
 * @property string $mode
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['email', 'token_hash', 'mode', 'expires_at', 'used_at'])]
class LoginLink extends Model
{
    /** @use HasFactory<LoginLinkFactory> */
    use HasFactory;

    public const MODES = ['signup', 'signin'];

    /** The frontend tells the visitor so: "Sign-in links work once, for 15 minutes". */
    public const LIFETIME_MINUTES = 15;

    /** The frontend unlocks "Resend the link" after this countdown. */
    public const RESEND_AFTER_SECONDS = 59;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Creates a link for the address and returns it with the plain token, which exists only in the email.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $email, string $mode): array
    {
        $token = Str::random(64);

        $link = self::create([
            'email' => $email,
            'token_hash' => self::hash($token),
            'mode' => $mode,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return [$link, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', self::hash($token))->first();
    }

    /**
     * Seconds until another link may be sent to the address; 0 when one may be sent now.
     */
    public static function secondsUntilResend(string $email): int
    {
        $last = self::where('email', $email)->latest('id')->first();

        if ($last === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInSeconds($last->created_at->addSeconds(self::RESEND_AFTER_SECONDS), false)));
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * The frontend's verify page for this link; a sign-in carries `mode=signin` so the page speaks in that voice.
     */
    public function url(string $token): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verify/confirm?'.http_build_query(array_filter([
            'token' => $token,
            'mode' => $this->mode === 'signin' ? 'signin' : null,
        ]));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
