<?php

namespace App\Models;

use Database\Factories\PricingCompareCellFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * What one plan gets on one comparison row: text when there is any, otherwise a tick or a dash.
 *
 * @property bool $included
 */
#[Fillable(['pricing_compare_row_id', 'plan_id', 'included', 'text'])]
class PricingCompareCell extends Model
{
    /** @use HasFactory<PricingCompareCellFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['text'];

    protected function casts(): array
    {
        return ['included' => 'boolean'];
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(PricingCompareRow::class, 'pricing_compare_row_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The text in the current locale with {contacts} as the plan's limit, or else the tick or dash.
     */
    public function valueFor(Plan $plan): bool|string
    {
        $text = (string) $this->getTranslation('text', app()->getLocale());

        return $text === '' ? $this->included : strtr($text, ['{contacts}' => number_format($plan->contacts_limit)]);
    }
}
