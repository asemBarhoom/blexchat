<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use App\Models\PricingCompareGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class PricingCompareGroupCollection extends ResourceCollection
{
    /**
     * @param  Collection<int, PricingCompareGroup>  $groups
     * @param  Collection<int, Plan>  $plans
     */
    public function __construct(Collection $groups, private readonly Collection $plans)
    {
        parent::__construct($groups);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(fn (PricingCompareGroup $group): array => (new PricingCompareGroupResource($group, $this->plans))->resolve($request))->values()->all();
    }

    /**
     * Groups arrive as models: the plans are needed to build each resource.
     */
    protected function collectResource($resource): mixed
    {
        return $this->collection = $resource;
    }
}
