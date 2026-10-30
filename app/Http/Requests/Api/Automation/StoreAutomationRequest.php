<?php

namespace App\Http\Requests\Api\Automation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:255'],
            'channel_id'              => ['required', 'integer', 'exists:channels,id'],
            'trigger_type'            => ['required', 'string', Rule::in(['comment_keyword', 'dm_keyword', 'story_mention'])],
            'keywords'                => ['nullable', 'array'],
            'keywords.*'              => ['string', 'max:100'],
            'actions'                 => ['required', 'array', 'min:1'],
            'actions.*.type'          => ['required', 'string', Rule::in(['reply_comment', 'send_dm', 'send_dm_with_button'])],
            'actions.*.message'       => ['required', 'string', 'max:2200'],
            'actions.*.button_label'  => ['nullable', 'string', 'max:20'],
            'actions.*.button_url'    => ['nullable', 'url'],
        ];
    }
}
