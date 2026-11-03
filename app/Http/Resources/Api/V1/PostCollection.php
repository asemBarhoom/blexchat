<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PostCollection extends ResourceCollection
{
    public $collects = PostResource::class;

    public function __construct(mixed $resource, private bool $synced)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'synced' => $this->synced,
            ],
        ];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }
}
