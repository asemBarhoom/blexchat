<?php

namespace App\Models;

use Database\Factories\ReferralCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a referrer earned on one payment of a customer they brought, at the rate set when it was paid.
 * Pending until the admin pays it out.
 */
#[Fillable(['referrer_id', 'referred_user_id', 'source', 'payment_cents', 'rate', 'amount_cents', 'currency', 'status', 'paid_at'])]
class ReferralCommission extends Model
{
    /** @use HasFactory<ReferralCommissionFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const STATUSES = [self::PENDING, self::PAID];

    protected function casts(): array
    {
        return [
            'payment_cents' => 'integer',
            'rate' => 'float',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * The customer whose payment earned it; null once they deleted their account.
     *
     * @return BelongsTo<User, $this>
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function markPaid(): void
    {
        if ($this->status === self::PENDING) {
            $this->update(['status' => self::PAID, 'paid_at' => now()]);
        }
    }
}
