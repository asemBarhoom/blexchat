<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'type' => $this->type,
            'content' => $this->content,
            'button' => $this->when(
                $this->button_label,
                fn () => ['label' => $this->button_label, 'url' => $this->button_url]
            ),
            // A handover line names why, in the request's language; `content` keeps the code.
            'reason' => $this->when(
                $this->type === Message::TYPE_HANDOVER,
                fn (): string => __("inbox.handover.{$this->content}")
            ),
            // The comment that opened the thread: the frontend's "· commented on your post".
            'via' => $this->when($this->type === Message::TYPE_COMMENT, 'comment'),
            'post' => $this->when(
                $this->type === Message::TYPE_COMMENT && $this->relationLoaded('comment'),
                fn (): ?PostResource => $this->comment?->post ? new PostResource($this->comment->post) : null
            ),
            'automated' => $this->automated,
            'automation_id' => $this->automation_id,
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
