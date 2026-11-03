<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SolutionCollection extends ResourceCollection
{
    public $collects = SolutionSummaryResource::class;
}
