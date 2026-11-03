<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Solution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A solution page in the list: enough for a menu or a card that links to it.
 *
 * @mixin Solution
 */
class SolutionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $identity = $this->identity();

        return [
            'slug' => $identity->slug,
            'name' => $identity->name,
            'shape' => $identity->shape,
            'tone' => $identity->tone,
            'headline' => $this->headline,
        ];
    }
}
