<?php

namespace App\Http\Requests\Api\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'channel' => ['sometimes', 'string', Rule::in(array_keys(Contact::PLATFORMS))],
            'tag' => ['sometimes', 'string', 'max:50'],
            'source' => ['sometimes', 'string', Rule::in(Contact::SOURCES)],
            'sort' => ['sometimes', 'string', Rule::in(Contact::SORTS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
