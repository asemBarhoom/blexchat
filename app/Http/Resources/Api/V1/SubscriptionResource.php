<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // The tier in force today: Free again once a trial has run out.
            'plan' => new PlanResource($this->effectivePlan()),
            'chosen_plan' => $this->plan->slug,
            'billing_period' => $this->billing_period,
            'status' => $this->trialEnded() ? 'trial_ended' : $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'trial_days_left' => $this->trialDaysLeft(),
        ];
    }
}
