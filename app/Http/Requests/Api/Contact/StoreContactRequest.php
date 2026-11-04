<?php

namespace App\Http\Requests\Api\Contact;

use App\Models\Contact;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create New Contact (W23): someone who has not messaged, reached by phone, so the owner must
 * confirm they agreed to hear from the business.
 */
class StoreContactRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'dial_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen(preg_replace('/\D/', '', (string) $value)) < 7) {
                    $fail('That number looks too short.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(Contact::GENDERS)],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactAttributes(): array
    {
        $phone = $this->string('dial_code')->trim().' '.$this->string('phone')->trim();

        return [
            'platform' => 'whatsapp',
            'name' => collect([$this->input('first_name'), $this->input('last_name')])->map(fn (?string $part): string => trim((string) $part))->filter()->implode(' '),
            'handle' => $phone,
            'phone' => $phone,
            'email' => $this->filled('email') ? trim($this->input('email')) : null,
            'gender' => $this->input('gender'),
            'added_manually' => true,
            'consented_at' => now(),
            'first_seen_at' => now(),
        ];
    }
}
