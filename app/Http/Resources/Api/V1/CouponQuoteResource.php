<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A coupon priced against one plan and billing period.
 *
 * @mixin Coupon
 */
class CouponQuoteResource extends JsonResource
{
    private Plan $plan;

    private string $billing = 'monthly';

    public function forPlan(Plan $plan, string $billing): static
    {
        $this->plan = $plan;
        $this->billing = $billing;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'percent_off' => $this->percent_off,
            'plan' => $this->plan->slug,
            'billing' => $this->billing,
            ...$this->resource->quote($this->plan, $this->billing),
        ];
    }
}
