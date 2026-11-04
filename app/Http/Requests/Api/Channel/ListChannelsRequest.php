<?php

namespace App\Http\Requests\Api\Channel;

use Illuminate\Foundation\Http\FormRequest;

class ListChannelsRequest extends FormRequest
{
    /**
     * The list only ever holds the signed-in owner's channels.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
