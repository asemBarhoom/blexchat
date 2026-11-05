<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A tier of the plan ladder. `features` holds a list of lines per language.
 *
 * @property string $slug
 * @property int $monthly_price
 * @property int $annual_price
 * @property int $contacts_limit
 * @property int $trial_days
 * @property bool $in_checkout
 */
#[Fillable(['slug', 'name', 'tagline', 'monthly_price', 'annual_price', 'contacts_limit', 'has_ai', 'has_email_capture', 'features', 'trial_days', 'is_recommended', 'in_checkout', 'sort', 'active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    /** Where an account lands with no plan chosen, and when a trial runs out. */
    public const FREE_SLUG = 'free';

    public const BILLING_PERIODS = ['monthly', 'annual'];

    /** @var list<string> */
    public array $translatable = ['name', 'tagline', 'features'];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'annual_price' => 'integer',
            'contacts_limit' => 'integer',
            'has_ai' => 'boolean',
            'has_email_capture' => 'boolean',
            'trial_days' => 'integer',
            'is_recommended' => 'boolean',
            'in_checkout' => 'boolean',
        ];
    }

    public static function free(): self
    {
        return self::where('slug', self::FREE_SLUG)->firstOrFail();
    }

    public function isFree(): bool
    {
        return $this->monthly_price === 0;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    /**
     * Whole US dollars a month for the billing period: the annual price is per month, billed yearly.
     */
    public function priceFor(string $billing): int
    {
        return $billing === 'annual' ? $this->annual_price : $this->monthly_price;
    }

    /**
     * The most paying a year up front saves over paying monthly, in whole percent: "Save up to 30%".
     *
     * @param  iterable<Plan>  $plans
     */
    public static function annualSavingUpTo(iterable $plans): int
    {
        return (int) collect($plans)
            ->reject(fn (Plan $plan): bool => $plan->isFree())
            ->map(fn (Plan $plan): int => (int) round((1 - $plan->annual_price / $plan->monthly_price) * 100))
            ->max();
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
