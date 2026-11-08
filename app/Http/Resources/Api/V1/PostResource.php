<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform_post_id' => $this->platform_post_id,
            'caption' => $this->caption,
            'thumbnail_url' => $this->thumbnail_url,
            'media_type' => $this->media_type,
            'likes' => $this->like_count,
            'comments' => $this->comment_count,
            'posted_at' => $this->posted_at?->toIso8601String(),
        ];
    }
}
