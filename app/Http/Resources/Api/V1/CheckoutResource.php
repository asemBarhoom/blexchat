<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The checkout settings, built from the plans on sale.
 *
 * @property Collection<int, Plan> $resource
 */
class CheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plans' => new PlanCollection($this->resource->where('in_checkout', true)->values()),
            'trial_reminder_days' => (int) Setting::get(Setting::TRIAL_REMINDER_DAYS),
            'annual_saving_up_to' => Plan::annualSavingUpTo($this->resource),
            'coupons_on_trial' => false,
        ];
    }
}
