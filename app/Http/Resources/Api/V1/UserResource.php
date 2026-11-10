<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'business_name' => $this->business_name,
            // agent: a teammate in the owner's inbox and contacts only (Settings · Team).
            'role' => $this->isTeammate() ? 'agent' : 'owner',
            // The language the frontend last asked for; emails and Blexchat AI use it.
            'locale' => $this->preferredLocale(),
            // The workspace this request worked in (BE-11): the X-Workspace header, else the first.
            'workspace' => ['id' => $this->workspace()->id, 'name' => $this->workspace()->name],
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
