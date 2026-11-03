<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FirstMove;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FirstMove
 */
class FirstMoveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->key,
            'trigger' => $this->trigger,
            'template' => $this->template,
            'wizard_path' => $this->wizardPath(),
            'title' => $this->title,
            'line' => $this->line,
            'incoming' => [
                'who' => $this->incoming_who,
                'text' => $this->incoming_text,
                'kind' => $this->incoming_kind,
            ],
            'reply' => $this->reply,
        ];
    }
}
