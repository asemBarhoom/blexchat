<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\LoginLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SendLoginLinkRequest extends FormRequest
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
            // The same check as the frontend's form, which wants a full address like name@yourbrand.com.
            'email' => ['required', 'string', 'email', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/'],
            'mode' => ['sometimes', 'string', Rule::in(LoginLink::MODES)],
            // A referral code the visitor arrived with; an unknown one is ignored rather than refused.
            'ref' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    public function email(): string
    {
        return Str::lower(trim($this->string('email')));
    }

    public function mode(): string
    {
        return $this->input('mode', 'signup');
    }

    public function referralCode(): ?string
    {
        return $this->filled('ref') ? (string) $this->input('ref') : null;
    }
}
