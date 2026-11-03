<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ChannelConnectedScreen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The "channel connected" screen (W07) copy for one platform, in the request's language.
 *
 * @mixin ChannelConnectedScreen
 */
class ChannelConnectedScreenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->platform,
            'channel_name' => $this->channel_name,
            'title' => $this->title,
            'lead' => $this->lead,
            'account_kind' => $this->account_kind,
            'status_label' => $this->status_label,
            'continue_label' => $this->continue_label,
            'connect_another_label' => $this->connect_another_label,
        ];
    }
}
