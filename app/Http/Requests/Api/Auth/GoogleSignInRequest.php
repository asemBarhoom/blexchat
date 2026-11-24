<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Validator;

class GoogleSignInRequest extends FormRequest
{
    /**
     * The redirect URI is signed inside Google's encrypted state so the token exchange uses the URI that started the flow.
     */
    public function redirectUri(): ?string
    {
        try {
            $state = json_decode(Crypt::decryptString((string) $this->input('state')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        return is_string($state['redirect_uri'] ?? null) ? $state['redirect_uri'] : null;
    }

    /**
     * The referral code sign-up started with (kept in the state), or one sent when finishing.
     */
    public function referralCode(): ?string
    {
        try {
            $state = json_decode(Crypt::decryptString((string) $this->input('state')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $state = [];
        }

        $code = $state['ref'] ?? $this->input('ref');

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            // A referral code the visitor arrived with; an unknown one is ignored rather than refused.
            'ref' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * The state must be one this API issued for Google sign-in, and still fresh.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('state')) {
                    return;
                }

                try {
                    $state = json_decode(Crypt::decryptString((string) $this->input('state')), true, flags: JSON_THROW_ON_ERROR);
                } catch (DecryptException|\JsonException) {
                    $state = null;
                }

                if (($state['purpose'] ?? null) !== 'google' || (int) ($state['expires'] ?? 0) < now()->timestamp) {
                    $validator->errors()->add('state', __('auth.google_failed'));
                }
            },
        ];
    }
}
