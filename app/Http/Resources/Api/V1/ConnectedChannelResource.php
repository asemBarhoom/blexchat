<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Channel;
use App\Models\ChannelConnectedScreen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One connected channel with everything the "channel connected" screen (W07) shows.
 *
 * @mixin Channel
 */
class ConnectedChannelResource extends JsonResource
{
    public function __construct(Channel $resource, private ?ChannelConnectedScreen $screen = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'account_id' => $this->account_id,
            'username' => $this->username,
            'profile_picture' => $this->imageUrl($this->profile_picture),
            'followers' => $this->followers,
            'status' => $this->status,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'screen' => $this->screen ? new ChannelConnectedScreenResource($this->screen) : null,
            'next_step' => $this->user->onboardingStep(),
        ];
    }

    /**
     * Platform avatars arrive as full URLs; seeded ones are paths on the public disk.
     */
    private function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : url(Storage::url($path));
    }
}
