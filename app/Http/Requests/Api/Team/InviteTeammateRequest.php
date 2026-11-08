<?php

namespace App\Http\Requests\Api\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Settings · Team: "Invite teammate".
 */
class InviteTeammateRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('email')) {
                return;
            }

            $email = Str::lower($this->input('email'));
            $owner = $this->user();

            if ($email === Str::lower($owner->email)) {
                $validator->errors()->add('email', __('team.own_email'));
            } elseif ($owner->teammates()->where('email', $email)->exists()) {
                $validator->errors()->add('email', __('team.already_member'));
            }
        }];
    }
}
