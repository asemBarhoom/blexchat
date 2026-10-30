<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'direction'     => $this->direction,
            'type'          => $this->type,
            'content'       => $this->content,
            'button'        => $this->when(
                $this->button_label,
                fn () => ['label' => $this->button_label, 'url' => $this->button_url]
            ),
            'automated'     => $this->automated,
            'automation_id' => $this->automation_id,
            'sent_at'       => $this->sent_at?->toIso8601String(),
        ];
    }
}
