<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A checkout discount code: a percentage off the plan's price. Payments are not built yet, so a
 * coupon is only checked and priced here, never redeemed.
 *
 * @property string $code
 * @property int $percent_off
 * @property bool $active
 * @property Carbon|null $expires_at
 * @property int|null $usage_limit
 * @property int $times_used
 */
#[Fillable(['code', 'percent_off', 'active', 'expires_at', 'usage_limit'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    public const INVALID = 'coupon_invalid';

    public const EXPIRED = 'coupon_expired';

    public const NOT_FOR_PLAN = 'coupon_not_for_plan';

    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
        ];
    }

    /**
     * Stored upper-case, so "blex20" and "BLEX20" are the same code.
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => self::normalize($value));
    }

    public static function normalize(string $code): string
    {
        return Str::upper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return self::where('code', self::normalize($code))->first();
    }

    /**
     * The plans this coupon is limited to; none means every paid plan.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() === true;
    }

    public function isUsedUp(): bool
    {
        return $this->usage_limit !== null && ($this->times_used ?? 0) >= $this->usage_limit;
    }

    /**
     * Why this coupon can't be used on the plan, as a stable error code, or null when it can.
     * There is no coupon on Free or on a plan sold with a free trial: nothing is charged to take a percentage of.
     */
    public function rejectionFor(Plan $plan): ?string
    {
        return match (true) {
            ! $this->active => self::INVALID,
            $this->isExpired(), $this->isUsedUp() => self::EXPIRED,
            $plan->isFree(), $plan->hasTrial() => self::NOT_FOR_PLAN,
            $this->plans->isNotEmpty() && ! $this->plans->contains($plan) => self::NOT_FOR_PLAN,
            default => null,
        };
    }

    /**
     * The amount taken off, rounded to the cent.
     */
    public function discountCents(int $priceCents): int
    {
        return (int) round($priceCents * $this->percent_off / 100);
    }

    /**
     * What the plan costs with this coupon. Prices are per month, as the checkout shows them;
     * `billed_total` is what one billing period charges (twelve months when billed yearly).
     *
     * @return array{list_price: float, discount: float, price: float, billed_total: float}
     */
    public function quote(Plan $plan, string $billing): array
    {
        $listCents = $plan->priceFor($billing) * 100;
        $discountCents = $this->discountCents($listCents);
        $priceCents = $listCents - $discountCents;
        $months = $billing === 'annual' ? 12 : 1;

        return [
            'list_price' => $listCents / 100,
            'discount' => $discountCents / 100,
            'price' => $priceCents / 100,
            'billed_total' => $priceCents * $months / 100,
        ];
    }
}
