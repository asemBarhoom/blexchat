<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SurveyAnswer;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SurveyQuestion
 */
class SurveyQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->key,
            'question' => $this->question,
            'hint' => $this->hint,
            'kind' => $this->kind,
            'other_option' => $this->other_option,
            'options' => $this->options->map(fn (SurveyOption $option): array => [
                'id' => $option->key,
                'label' => $option->label,
                'note' => $option->note,
                'icon' => $option->icon,
                'icon_url' => $option->iconImageUrl(),
                'icon_box' => $option->icon_box,
            ])->all(),
            // The signed-in owner's answer, so Back and a reload keep what was picked.
            'answer' => $this->whenLoaded('answers', fn (): ?array => ($answer = $this->answers->first()) instanceof SurveyAnswer
                ? ['options' => $answer->options, 'other' => $answer->other_text]
                : null),
        ];
    }
}
