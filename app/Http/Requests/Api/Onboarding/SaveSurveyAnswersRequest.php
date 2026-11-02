<?php

namespace App\Http\Requests\Api\Onboarding;

use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSurveyAnswersRequest extends FormRequest
{
    /** @var Collection<int, SurveyQuestion>|null */
    private ?Collection $questions = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A skipped question is simply left out, so an empty `answers` list (Skip on every step) is valid.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*' => ['array:question,options,other'],
            'answers.*.question' => ['required', 'string', 'distinct', Rule::in($this->questions()->keys()->all())],
            'answers.*.options' => ['required', 'array', 'min:1'],
            'answers.*.options.*' => ['required', 'string', 'distinct'],
            'answers.*.other' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Each answer must use its own question's options, one for a radio question, and free text only
     * beside the option that asks for it.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('answers') as $index => $answer) {
                    $question = $this->questions()->get($answer['question']);
                    $name = $question->question;
                    $keys = $question->options->pluck('key');

                    if (collect($answer['options'])->diff($keys)->isNotEmpty()) {
                        $validator->errors()->add("answers.{$index}.options", __('onboarding.survey.unknown_option', ['question' => $name]));
                    } elseif (! $question->allowsSeveral() && count($answer['options']) > 1) {
                        $validator->errors()->add("answers.{$index}.options", __('onboarding.survey.one_only', ['question' => $name]));
                    } elseif (filled($answer['other'] ?? null) && ! in_array($question->other_option, $answer['options'], true)) {
                        $option = $question->options->firstWhere('key', $question->other_option)?->label ?? $question->other_option;
                        $validator->errors()->add("answers.{$index}.other", __('onboarding.survey.other_not_picked', ['question' => $name, 'option' => $option]));
                    }
                }
            },
        ];
    }

    /**
     * The survey as it stands, keyed by question key.
     *
     * @return Collection<string, SurveyQuestion>
     */
    public function questions(): Collection
    {
        return $this->questions ??= SurveyQuestion::visible()->with('options')->get()->keyBy('key');
    }
}
