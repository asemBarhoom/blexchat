<?php

namespace App\Http\Requests\Api\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Changing the plan or the billing period after onboarding. Whatever is left out stays as it is.
 */
class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required_without:billing', 'string', Rule::exists(Plan::class, 'slug')->where('active', true)],
            'billing' => ['required_without:plan', 'string', Rule::in(Plan::BILLING_PERIODS)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('plan') && $this->currentSubscription() === null) {
                    $validator->errors()->add('plan', __('validation.required', ['attribute' => 'plan']));
                }
            },
        ];
    }

    public function plan(): Plan
    {
        if ($this->filled('plan')) {
            return Plan::where('slug', $this->string('plan'))->firstOrFail();
        }

        return $this->currentSubscription()->plan;
    }

    public function billing(): string
    {
        return $this->input('billing', $this->currentSubscription()?->billing_period ?? 'monthly');
    }

    private function currentSubscription(): ?Subscription
    {
        return $this->user()->subscription;
    }
}
