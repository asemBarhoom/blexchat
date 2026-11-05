<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => [
                'id' => $this->channel_id,
                'platform' => $this->channel?->platform,
            ],
            'contact' => [
                'name' => $this->contact_name,
                'avatar' => $this->contact_avatar,
            ],
            'last_message' => $this->last_message,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread' => $this->unread_count,
            'status' => $this->status,
            'state' => $this->state,
        ];
    }
}
