<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ChannelOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChannelOption
 */
class ChannelOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->platform,
            'name' => $this->name,
            'line' => $this->line,
            'connected' => (bool) $this->connected,
        ];
    }
}
