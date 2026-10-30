<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'platform'        => $this->platform,
            'username'        => $this->username,
            'profile_picture' => $this->profile_picture,
            'followers'       => $this->followers,
            'status'          => $this->status,
            'connected_at'    => $this->connected_at?->toIso8601String(),
        ];
    }
}
