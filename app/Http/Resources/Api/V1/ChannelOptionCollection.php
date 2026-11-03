<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ChannelOptionCollection extends ResourceCollection
{
    public $collects = ChannelOptionResource::class;
}
