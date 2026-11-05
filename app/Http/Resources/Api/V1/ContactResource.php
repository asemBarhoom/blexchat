<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'handle' => $this->displayHandle(),
            'channel' => $this->platform,
            'channel_id' => $this->channel_id,
            'avatar' => $this->avatar,
            'tags' => $this->tags ?? [],
            // The automation that brought them in; null when they messaged directly or were added.
            'source' => $this->sourceAutomation?->name,
            'source_automation_id' => $this->source_automation_id,
            'added' => $this->added_manually,
            'gender' => $this->gender,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'language' => $this->language,
            'subscribed' => $this->subscribed,
            'conversation' => $this->whenLoaded('conversation', fn (): ?array => $this->conversation === null ? null : [
                'id' => $this->conversation->id,
                'status' => $this->conversation->status,
                // human is the contact page's "Needs you".
                'state' => $this->conversation->state,
                'messages_count' => $this->conversation->messages_count,
            ]),
            'first_seen_at' => $this->first_seen_at->toIso8601String(),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
