<?php

namespace App\Http\Requests\Api\Automation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            // Into one of the owner's folders, or null to take it out of its folder.
            'automation_folder_id' => ['sometimes', 'nullable', 'integer', Rule::exists('automation_folders', 'id')->where('user_id', $this->user()->id)],
            'keywords' => ['sometimes', 'nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'actions' => ['sometimes', 'array', 'min:1'],
            'actions.*.type' => ['required_with:actions', 'string', Rule::in(['reply_comment', 'send_dm', 'send_dm_with_button'])],
            'actions.*.message' => ['required_with:actions', 'string', 'max:2200'],
            'actions.*.button_label' => ['nullable', 'string', 'max:20'],
            'actions.*.button_url' => ['nullable', 'url'],
            'extras' => ['sometimes', 'array'],
            'extras.ai_goal' => ['sometimes', 'nullable', 'string', 'max:500'],
            'extras.review_before_send' => ['sometimes', 'boolean'],
            'extras.ai_comments' => ['sometimes', 'boolean'],
        ];
    }
}
