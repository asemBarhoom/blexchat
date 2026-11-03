<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Solution;
use App\Models\SolutionFaqItem;
use App\Models\SolutionLabel;
use App\Models\SolutionStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * A whole solution page, shaped like the frontend's `Solution` (solutionsContent.ts).
 *
 * @mixin Solution
 */
class SolutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new SolutionSummaryResource($this->resource))->toArray($request),
            'sample' => ['handle' => $this->sample_handle, 'avatar' => $this->sampleAvatarUrl()],
            'hero' => ['headline' => $this->headline, 'supporting' => $this->supporting],
            'automations' => SolutionAutomationResource::collection($this->automations),
            'steps' => $this->steps->map(fn (SolutionStep $step): string => $step->text)->all(),
            'faq' => $this->faqItems->map(fn (SolutionFaqItem $item): array => ['q' => $item->question, 'a' => $item->answer])->all(),
            'closing' => $this->closing,
            // The words the template writes around the content, the same on every page.
            'labels' => SolutionLabel::current()?->copyIn(App::getLocale()) ?? [],
        ];
    }
}
