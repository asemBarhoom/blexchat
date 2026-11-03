<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PlanCollection extends ResourceCollection
{
    public $collects = PlanResource::class;

    /**
     * "Save up to 30%" on the plan screen (W09) and the pricing page, computed from these plans.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['meta' => ['annual_saving_up_to' => Plan::annualSavingUpTo($this->collection->pluck('resource'))]];
    }
}
