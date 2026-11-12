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
            'available' => $this->available(),
            'pending_reason' => $this->available() ? null : trans('channels.provider_pending', ['channel' => $this->name, 'provider' => in_array($this->platform, ['messenger', 'whatsapp'], true) ? 'Meta' : 'TikTok']),
        ];
    }

    private function available(): bool
    {
        if ($this->platform === 'instagram') {
            return true;
        }

        return (config('services.channels.oauth_driver') === 'fake' && ! app()->isProduction())
            || (config('services.channels.oauth_driver') === 'provider' && filled(config("services.{$this->platform}.client_id")) && filled(config('services.channels.oauth_redirect_url')));
    }
}
