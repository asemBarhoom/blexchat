<?php

namespace App\Http\Requests\Api\Checkout;

use App\Models\Plan;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutSessionRequest extends FormRequest
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
            // Paid plans on sale only: Free never goes through a payment page.
            'plan' => ['required', 'string', Rule::exists(Plan::class, 'slug')->where('active', true)->where(fn (Builder $query) => $query->where('monthly_price', '>', 0))],
            'billing' => ['sometimes', 'string', Rule::in(Plan::BILLING_PERIODS)],
            'coupon' => ['nullable', 'string', 'max:50'],
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
