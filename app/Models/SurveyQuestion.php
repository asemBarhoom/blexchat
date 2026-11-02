<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\SurveyQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A step of the onboarding survey (W08): one answer for `radio`, any number for `multi` and `grid`.
 *
 * @property string $key
 * @property string $kind
 * @property string|null $other_option
 */
#[Fillable(['key', 'question', 'hint', 'kind', 'other_option', 'sort', 'active'])]
class SurveyQuestion extends Model
{
    /** @use HasFactory<SurveyQuestionFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    public const KINDS = ['radio', 'multi', 'grid'];

    /** @var list<string> */
    public array $translatable = ['question', 'hint'];

    public function allowsSeveral(): bool
    {
        return $this->kind !== 'radio';
    }

    public function options(): HasMany
    {
        return $this->hasMany(SurveyOption::class)->orderBy('sort')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
