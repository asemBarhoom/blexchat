<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use App\Models\PricingCompareGroup;
use App\Models\PricingCompareRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin PricingCompareGroup
 */
class PricingCompareGroupResource extends JsonResource
{
    /**
     * @param  Collection<int, Plan>  $plans
     */
    public function __construct(PricingCompareGroup $group, private readonly Collection $plans)
    {
        parent::__construct($group);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->key,
            'title' => $this->title,
            'rows' => $this->rows->map(fn (PricingCompareRow $row): array => (new PricingCompareRowResource($row, $this->plans))->resolve($request))->all(),
        ];
    }
}
