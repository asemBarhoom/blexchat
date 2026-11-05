<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Automation
 */
class AutomationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel_id' => $this->channel_id,
            'trigger_type' => $this->trigger_type,
            'kind' => $this->kind(),
            'post_mode' => $this->post_mode,
            'post_id' => $this->post_id,
            'template' => $this->template?->key,
            'keywords' => $this->keywords ?? [],
            'actions' => $this->actions ?? [],
            'active' => $this->active,
            'wizard' => [...$this->wizardAnswers(), 'name' => $this->name, 'channel_id' => $this->channel_id],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'trashed_at' => $this->deleted_at?->toIso8601String(),
            'purges_at' => $this->purgesAt()?->toIso8601String(),
        ];
    }
}
