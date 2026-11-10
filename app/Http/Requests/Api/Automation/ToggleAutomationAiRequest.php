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
        return $this->user()->workspace()->holds($this->route('automation'));
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
