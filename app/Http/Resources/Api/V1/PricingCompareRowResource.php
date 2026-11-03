<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use App\Models\PricingCompareRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin PricingCompareRow
 */
class PricingCompareRowResource extends JsonResource
{
    /**
     * @param  Collection<int, Plan>  $plans  the plans on sale, one cell each
     */
    public function __construct(PricingCompareRow $row, private readonly Collection $plans)
    {
        parent::__construct($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->key,
            'label' => $this->label,
            'cells' => $this->plans->mapWithKeys(fn (Plan $plan): array => [$plan->slug => $this->cellFor($plan)])->all(),
        ];
    }
}
