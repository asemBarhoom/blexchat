<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\PricingCompareRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A line of the plan comparison table: what each plan gets, from its cells or, with a
 * `source`, straight from a plan field, so the table never repeats what the plans say.
 *
 * @property string $key
 * @property string|null $source
 */
#[Fillable(['pricing_compare_group_id', 'key', 'label', 'source', 'sort', 'active'])]
class PricingCompareRow extends Model
{
    /** @use HasFactory<PricingCompareRowFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    public const SOURCES = ['has_ai', 'paid'];

    /** @var list<string> */
    public array $translatable = ['label'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PricingCompareGroup::class, 'pricing_compare_group_id');
    }

    public function cells(): HasMany
    {
        return $this->hasMany(PricingCompareCell::class);
    }

    /**
     * A tick (true), a dash (false) or text. A plan with no cell gets a dash.
     */
    public function cellFor(Plan $plan): bool|string
    {
        return match ($this->source) {
            'has_ai' => $plan->has_ai,
            'paid' => ! $plan->isFree(),
            default => $this->cells->firstWhere('plan_id', $plan->id)?->valueFor($plan) ?? false,
        };
    }
}
