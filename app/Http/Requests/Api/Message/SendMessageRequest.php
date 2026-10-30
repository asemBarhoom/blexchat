<?php

namespace App\Http\Requests\Api\Message;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', 'string', Rule::in(['text', 'text_with_button'])],
            'content'      => ['required', 'string', 'max:2200'],
            'button_label' => ['required_if:type,text_with_button', 'nullable', 'string', 'max:20'],
            'button_url'   => ['required_if:type,text_with_button', 'nullable', 'url'],
        ];
    }
}
