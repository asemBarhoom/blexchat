<?php

namespace App\Http\Requests\Api\Conversation;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateConversationRequest extends FormRequest
{
    /** @var list<string> */
    private const FIELDS = ['status', 'read', 'starred', 'labels'];

    /**
     * Only the owner of the conversation's channel may change it.
     */
    public function authorize(): bool
    {
        return $this->user()->channels()->whereKey($this->route('conversation')->channel_id)->exists();
    }

    /**
     * "Mark done" and "Reopen" send `status`; opening a thread sends `read: true` (false changes nothing);
     * the star sends `starred`; labels are sent whole. Any one may be sent alone.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(Conversation::STATUSES)],
            'read' => ['sometimes', 'boolean'],
            'starred' => ['sometimes', 'boolean'],
            // Sent whole; [] removes every label.
            'labels' => ['sometimes', 'array', 'max:'.Conversation::MAX_LABELS],
            'labels.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
        ];
    }

    /**
     * A request that changes nothing is a mistake, named on the two fields the inbox sends most.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(self::FIELDS)) {
                $message = 'Send status, read, starred or labels.';
                $validator->errors()->add('status', $message)->add('read', $message);
            }
        }];
    }

    /**
     * Normalises labels to trimmed text, so "VIP " and "VIP" are one label.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('labels'))) {
            $this->merge(['labels' => array_values(array_map(fn (mixed $label): mixed => is_string($label) ? trim($label) : $label, $this->input('labels')))]);
        }
    }
}
