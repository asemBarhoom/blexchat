<?php

namespace App\Http\Requests\Api\Inbox;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInboxSettingsRequest extends FormRequest
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
            'accepting_new' => ['required', 'boolean'],
        ];
    }
}
