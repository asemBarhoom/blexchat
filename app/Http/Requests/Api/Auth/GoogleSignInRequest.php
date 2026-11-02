<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Validator;

class GoogleSignInRequest extends FormRequest
{
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
