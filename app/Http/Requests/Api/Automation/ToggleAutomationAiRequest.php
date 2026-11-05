<?php

namespace App\Http\Requests\Api\Automation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The automation detail's Blexchat AI switch.
 */
class ToggleAutomationAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('automation')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
