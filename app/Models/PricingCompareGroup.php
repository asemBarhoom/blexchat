<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\PricingCompareGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A heading of the pricing page's plan comparison table (Audience, Automation…) and its rows.
 *
 * @property string $key
 */
#[Fillable(['key', 'title', 'sort', 'active'])]
class PricingCompareGroup extends Model
{
    /** @use HasFactory<PricingCompareGroupFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    /** @var list<string> */
    public array $translatable = ['title'];

    public function rows(): HasMany
    {
        return $this->hasMany(PricingCompareRow::class)->orderBy('sort')->orderBy('id');
    }
}
