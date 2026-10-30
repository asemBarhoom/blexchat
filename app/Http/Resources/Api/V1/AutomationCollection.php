<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AutomationCollection extends ResourceCollection
{
    public $collects = AutomationResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total'    => $this->total(),
                'page'     => $this->currentPage(),
                'per_page' => $this->perPage(),
            ],
        ];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }
}
