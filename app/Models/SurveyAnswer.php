<?php

namespace App\Models;

use Database\Factories\SurveyAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string> $options The picked option keys.
 * @property string|null $other_text
 */
#[Fillable(['user_id', 'survey_question_id', 'options', 'other_text'])]
class SurveyAnswer extends Model
{
    /** @use HasFactory<SurveyAnswerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
