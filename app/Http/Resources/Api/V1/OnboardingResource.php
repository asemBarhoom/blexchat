<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class OnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'next_step' => $this->onboardingStep(),
            'channels' => ChannelResource::collection($this->channels),
            'survey_completed_at' => $this->survey_completed_at?->toIso8601String(),
            'subscription' => $this->subscription ? new SubscriptionResource($this->subscription) : null,
        ];
    }
}
