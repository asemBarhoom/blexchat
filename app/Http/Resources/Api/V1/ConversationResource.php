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
            // The inbox's contact panel. id is null only for a conversation that predates contacts.
            'contact' => [
                'id' => $this->contact_id,
                'name' => $this->contact?->name ?? $this->contact_name,
                'handle' => $this->contact?->handle,
                'avatar' => $this->contact?->avatar ?? $this->contact_avatar,
                'tags' => $this->contact?->tags ?? [],
                'city' => $this->contact?->city,
                // The automation that brought them in ("Started by …"); null when they messaged directly.
                'source' => $this->contact?->sourceAutomation?->name,
                'source_automation_id' => $this->contact?->source_automation_id,
                'last_active_at' => $this->contact?->last_active_at?->toIso8601String(),
            ],
            'last_message' => $this->last_message,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread' => $this->unread_count,
            'status' => $this->status,
            'state' => $this->state,
        ];
    }
}
