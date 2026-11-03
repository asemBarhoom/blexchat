<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AutomationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AutomationTemplate
 */
class AutomationTemplateResource extends JsonResource
{
    /**
     * `draft` holds the wizard answers the template fills, in the wizard's own field names; null
     * for a flow template.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->key,
            'name' => $this->name,
            'line' => $this->line,
            'build' => $this->build,
            'goal' => $this->goal,
            'trigger' => $this->trigger,
            'locked' => $this->locked,
            'draft' => $this->fillsWizard() ? [
                'trigger' => $this->match,
                'words' => $this->match === 'words' ? ($this->keywords ?? []) : [],
                'public_reply' => $this->trigger === 'comment' && filled($this->public_reply),
                'public_reply_text' => $this->trigger === 'comment' ? $this->public_reply : null,
                'dm_text' => $this->dm_text,
                'dm_button' => $this->dm_button,
                'link_url' => $this->link_url,
            ] : null,
        ];
    }
}
