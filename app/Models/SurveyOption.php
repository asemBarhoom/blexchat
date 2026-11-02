<?php

namespace App\Models;

use Database\Factories\SurveyOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $key
 */
#[Fillable(['survey_question_id', 'key', 'label', 'note', 'sort'])]
class SurveyOption extends Model
{
    /** @use HasFactory<SurveyOptionFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['label', 'note'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
