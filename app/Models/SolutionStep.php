<?php

namespace App\Models;

use Database\Factories\SolutionStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A numbered step of a solution page's "How it works".
 */
#[Fillable(['solution_id', 'text', 'sort'])]
class SolutionStep extends Model
{
    /** @use HasFactory<SolutionStepFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['text'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(Solution::class);
    }
}
