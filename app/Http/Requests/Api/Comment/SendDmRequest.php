<?php

namespace App\Http\Requests\Api\Comment;

use Illuminate\Foundation\Http\FormRequest;

class SendDmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'              => ['required', 'string', 'max:2200'],
            'include_public_reply' => ['boolean'],
        ];
    }
}
