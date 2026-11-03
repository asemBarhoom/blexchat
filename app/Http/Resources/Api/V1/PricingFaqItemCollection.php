<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PricingFaqItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class PricingFaqItemCollection extends ResourceCollection
{
    /**
     * @param  Collection<int, PricingFaqItem>  $items
     * @param  array<string, int|string>  $figures
     */
    public function __construct(Collection $items, private readonly array $figures)
    {
        parent::__construct($items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(fn (PricingFaqItem $item): array => (new PricingFaqItemResource($item, $this->figures))->resolve($request))->values()->all();
    }

    /**
     * Items arrive as models: the figures are needed to build each resource.
     */
    protected function collectResource($resource): mixed
    {
        return $this->collection = $resource;
    }
}
