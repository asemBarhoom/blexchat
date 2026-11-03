<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\PricingFaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A question of the pricing page FAQ. Answers name prices and limits by placeholder (PricingPage::FIGURES).
 */
#[Fillable(['question', 'answer', 'sort', 'active'])]
class PricingFaqItem extends Model
{
    /** @use HasFactory<PricingFaqItemFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    /** @var list<string> */
    public array $translatable = ['question', 'answer'];
}
