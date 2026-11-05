<?php

namespace App\Http\Requests\Api\Conversation;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    /**
     * Only the owner of the conversation's channel may change it.
     */
    public function authorize(): bool
    {
        return $this->user()->channels()->whereKey($this->route('conversation')->channel_id)->exists();
    }

    /**
     * "Mark done" and "Reopen" send `status`; opening a thread sends `read: true` (false changes nothing). Either may be sent alone.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required_without:read', 'string', Rule::in(Conversation::STATUSES)],
            'read' => ['required_without:status', 'boolean'],
        ];
    }
}
