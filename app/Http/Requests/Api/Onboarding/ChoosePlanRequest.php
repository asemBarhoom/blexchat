<?php

namespace App\Http\Requests\Api\Onboarding;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChoosePlanRequest extends FormRequest
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
            'plan' => ['required', 'string', Rule::exists(Plan::class, 'slug')->where('active', true)],
            'billing' => ['sometimes', 'string', Rule::in(Plan::BILLING_PERIODS)],
        ];
    }

    public function plan(): Plan
    {
        return Plan::where('slug', $this->string('plan'))->firstOrFail();
    }

    public function billing(): string
    {
        return $this->input('billing', 'monthly');
    }
}
