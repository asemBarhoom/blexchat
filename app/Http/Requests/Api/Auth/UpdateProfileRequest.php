<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Either field may be sent alone; onboarding asks for the name and the business name on one screen.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn (mixed $value): mixed => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value,
            $this->only(['name', 'business_name']),
        ));
    }
}
