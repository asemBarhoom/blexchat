<?php

namespace App\Http\Requests\Api\Channel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Instagram connects through OAuth (InstagramConnectionController) so its webhooks get subscribed.
            'platform' => ['required', 'string', Rule::in(['whatsapp', 'tiktok', 'messenger'])],
            'access_token' => ['required', 'string'],
            'account_id' => ['required', 'string'],
        ];
    }
}
