<?php

namespace App\Http\Requests\Api\Contact;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Contacts · Import: a CSV of people who agreed to hear from the business, as on Create New Contact.
 */
class ImportContactsRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
            'consent' => ['accepted'],
        ];
    }
}
