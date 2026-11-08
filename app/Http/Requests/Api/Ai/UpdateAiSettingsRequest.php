<?php

namespace App\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Blexchat AI · Business info: what the AI may answer customers from.
 */
class UpdateAiSettingsRequest extends FormRequest
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
            'business_info' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }
}
