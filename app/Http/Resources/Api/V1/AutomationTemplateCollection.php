<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AutomationTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The picker's shelf, with the wizard's starters beside it keyed by trigger.
 */
class AutomationTemplateCollection extends ResourceCollection
{
    public $collects = AutomationTemplateResource::class;

    /**
     * @param  Collection<int, AutomationTemplate>  $starters
     */
    public function __construct(mixed $resource, private Collection $starters)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'starters' => $this->starters
                ->keyBy('trigger')
                ->map(fn (AutomationTemplate $starter): AutomationTemplateResource => new AutomationTemplateResource($starter)),
        ];
    }
}
