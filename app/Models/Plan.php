<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 */
#[Fillable(['slug', 'name', 'tagline', 'monthly_price', 'annual_price', 'contacts_limit', 'has_ai', 'features', 'trial_days', 'is_recommended', 'sort', 'active'])]
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
            'trial_days' => 'integer',
            'is_recommended' => 'boolean',
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
