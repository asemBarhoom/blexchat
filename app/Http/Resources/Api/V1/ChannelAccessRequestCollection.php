<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ChannelAccessRequestCollection extends ResourceCollection
{
    public $collects = ChannelAccessRequestResource::class;
}
