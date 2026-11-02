<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'monthly_price' => $this->monthly_price,
            'annual_price' => $this->annual_price,
            'annual_total' => $this->annual_price * 12,
            'currency' => 'USD',
            'contacts_limit' => $this->contacts_limit,
            'has_ai' => $this->has_ai,
            'features' => $this->features ?? [],
            'trial_days' => $this->trial_days,
            'is_free' => $this->isFree(),
            'is_recommended' => $this->is_recommended,
        ];
    }
}
