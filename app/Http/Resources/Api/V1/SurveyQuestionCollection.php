<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SurveyQuestionCollection extends ResourceCollection
{
    public $collects = SurveyQuestionResource::class;
}
