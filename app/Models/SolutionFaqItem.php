<?php

namespace App\Models;

use Database\Factories\SolutionFaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A question and answer of a solution page's FAQ.
 */
#[Fillable(['solution_id', 'question', 'answer', 'sort'])]
class SolutionFaqItem extends Model
{
    /** @use HasFactory<SolutionFaqItemFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['question', 'answer'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(Solution::class);
    }
}
