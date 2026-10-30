<?php

namespace App\Http\Requests\Api\Automation;

use Illuminate\Foundation\Http\FormRequest;

class ToggleAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
