<?php

namespace App\Http\Requests\Api\Channel;

use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChannelAccessRequest extends FormRequest
{
    private ?Channel $channel = null;

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
            'token' => ['required', 'string'],
        ];
    }

    /**
     * The token must be the one handed to this owner with an `account_taken` refusal, and still fresh.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('token')) {
                    return;
                }

                $this->channel = ChannelAccessRequest::channelFromToken((string) $this->input('token'), $this->user());

                if ($this->channel === null) {
                    $validator->errors()->add('token', __('channels.access_request.token_invalid'));
                }
            },
        ];
    }

    /**
     * The other workspace's channel asked for; only after validation passed.
     */
    public function channel(): Channel
    {
        return $this->channel;
    }
}
