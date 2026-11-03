<?php

namespace App\Http\Requests\Api\Channel;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Validator;

class ConnectInstagramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Instagram returns either `code`, or `error` (with `error_reason`) when the owner cancelled or refused.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:error', 'nullable', 'string'],
            'state' => ['required', 'string'],
            'error' => ['nullable', 'string', 'max:255'],
            'error_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The state must be the one issued to this user by the authorize step, and still fresh.
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

                if ((int) ($state['user'] ?? 0) !== (int) $this->user()->id || (int) ($state['expires'] ?? 0) < now()->timestamp) {
                    $validator->errors()->add('state', __('channels.connect.state_invalid', ['channel' => 'Instagram']));
                }
            },
        ];
    }

    public function wasDenied(): bool
    {
        return $this->filled('error');
    }
}
