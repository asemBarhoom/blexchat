<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckoutSession
 */
class CheckoutSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->payment_session_id,
            'url' => $this->url,
            'status' => $this->status,
            'plan' => $this->plan->slug,
            'billing' => $this->billing_period,
            'coupon' => $this->coupon?->code,
            // What the first payment charges, in the currency's main unit: 374.4, not 37440 cents.
            'amount_total' => $this->amount_total / 100,
            'currency' => $this->currency,
            // Once paid: the plan now in place.
            'subscription' => $this->when($this->isComplete(), fn () => new SubscriptionResource($this->user->subscription->load('plan'))),
        ];
    }
}
