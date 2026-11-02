<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PlanCollection extends ResourceCollection
{
    public $collects = PlanResource::class;
}
