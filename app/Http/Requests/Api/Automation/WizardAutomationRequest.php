<?php

namespace App\Http\Requests\Api\Automation;

use App\Models\Automation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The wizard's answers (W11–W15, Review), the same for a new automation and an edit. `kind` and
 * `channel_id` are fixed for an edit, so the edit request takes them from the automation.
 */
abstract class WizardAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The automation being edited; null for a new one.
     */
    abstract protected function current(): ?Automation;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $kind = $this->input('kind');
        $isOn = fn (string $switch): bool => $this->boolean($switch);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->where('user_id', $this->user()->id)],
            'kind' => ['required', 'string', Rule::in(Automation::KINDS)],
            'post_mode' => [Rule::excludeIf($kind !== 'comment'), 'required', 'string', Rule::in(Automation::POST_MODES)],
            'post_id' => [
                Rule::excludeIf($kind !== 'comment' || $this->input('post_mode') !== 'specific'),
                'required', 'integer', Rule::exists('posts', 'id')->where('channel_id', $this->integer('channel_id')),
            ],
            'trigger' => ['required', 'string', Rule::in(['words', 'any'])],
            'words' => [Rule::excludeIf($this->input('trigger') !== 'words'), 'required', 'array', 'min:1', 'max:20'],
            'words.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'public_reply' => [Rule::excludeIf($kind !== 'comment'), 'boolean'],
            'public_reply_text' => [Rule::excludeIf($kind !== 'comment' || ! $isOn('public_reply')), 'required', 'string', 'max:300'],
            'dm_text' => ['required', 'string', 'max:640'],
            'dm_button' => ['nullable', 'string', 'max:20', 'required_with:link_url'],
            'link_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'follow_up_after' => [Rule::excludeIf(! $isOn('follow_up')), 'required', 'string', Rule::in(Automation::FOLLOW_UP_AFTER)],
        ];

        foreach (Automation::EXTRAS as $switch => $fields) {
            $rules[$switch] = ['sometimes', 'boolean'];
            foreach ($fields as $field) {
                $rules[$field] ??= [Rule::excludeIf(! $isOn($switch)), 'required', 'string', 'max:'.(str_ends_with($field, '_button') ? 20 : 640)];
            }
        }

        return $rules;
    }

    /**
     * Paid answers need a paid plan, and the email question a plan with email capture (Pro up). One
     * already saved on the automation stays, so an edit after a trial ends or a downgrade does not
     * fail over an extra the owner did not touch.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $plan = $this->user()->subscription?->effectivePlan();
                $saved = $this->current()?->wizardAnswers() ?? [];
                $isNewlyOn = fn (string $switch): bool => $this->boolean($switch) && ! ($saved[$switch] ?? false);

                if ($isNewlyOn('ask_email') && ! ($plan?->has_email_capture ?? false)) {
                    $validator->errors()->add('ask_email', __('automation.email_capture_extra'));
                }

                if ($plan !== null && ! $plan->isFree()) {
                    return;
                }

                if (in_array($this->input('post_mode'), Automation::PAID_POST_MODES, true)
                    && $this->input('kind') === 'comment'
                    && ($saved['post_mode'] ?? null) !== $this->input('post_mode')) {
                    $validator->errors()->add('post_mode', __('automation.paid_post_mode'));
                }

                foreach (array_keys(Automation::EXTRAS) as $switch) {
                    if ($isNewlyOn($switch) && ! $validator->errors()->has($switch)) {
                        $validator->errors()->add($switch, __('automation.paid_extra'));
                    }
                }
            },
        ];
    }

    /**
     * Storage attributes for the validated answers.
     *
     * @return array<string, mixed>
     */
    public function automationAttributes(): array
    {
        return Automation::fromWizard($this->validated());
    }
}
