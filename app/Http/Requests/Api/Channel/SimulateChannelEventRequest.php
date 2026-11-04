<?php

namespace App\Http\Requests\Api\Channel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateChannelEventRequest extends FormRequest
{
    /**
     * Only the channel's owner may simulate events on it.
     */
    public function authorize(): bool
    {
        return $this->route('channel')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['comment', 'message'])],
            'text' => ['required', 'string', 'max:1000'],
            // Instagram usernames: letters, digits, dots and underscores.
            'from' => ['sometimes', 'string', 'max:30', 'regex:/^[A-Za-z0-9._]+$/'],
            'post' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function from(): string
    {
        return $this->input('from', 'test_customer');
    }
}
