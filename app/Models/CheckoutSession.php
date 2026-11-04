<?php

namespace App\Models;

use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment page opened for a paid plan: a Paddle transaction, or the fake driver's page. It stays
 * `open` until the provider reports it paid; completing it puts the plan in place, once.
 *
 * @property string $billing_period
 * @property string $payment_provider
 * @property string $payment_session_id
 * @property string|null $url
 * @property int $amount_total
 * @property string $currency
 * @property string $status
 * @property string|null $payment_subscription_id
 * @property Carbon|null $completed_at
 */
#[Fillable(['user_id', 'payment_provider', 'plan_id', 'coupon_id', 'billing_period', 'payment_session_id', 'url', 'amount_total', 'currency', 'status', 'payment_subscription_id', 'completed_at'])]
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    public const STATUSES = ['open', 'complete', 'expired'];

    protected function casts(): array
    {
        return [
            'amount_total' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
