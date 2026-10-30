<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'author'         => $this->author_name,
            'text'           => $this->text,
            'posted_at'      => $this->posted_at?->toIso8601String(),
            'automated_reply' => $this->automated_reply,
            'automation_id'  => $this->automation_id,
        ];
    }
}
