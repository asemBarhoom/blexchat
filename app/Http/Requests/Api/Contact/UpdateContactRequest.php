<?php

namespace App\Http\Requests\Api\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tags are what the contact page edits; the details are editable too.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'name' => ['sometimes', 'string', 'max:200'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(Contact::GENDERS)],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'subscribed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactAttributes(): array
    {
        $attributes = $this->validated();

        if (isset($attributes['tags'])) {
            $attributes['tags'] = array_values(array_unique(array_map('trim', $attributes['tags'])));
        }

        return $attributes;
    }
}
