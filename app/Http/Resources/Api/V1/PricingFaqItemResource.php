<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PricingFaqItem;
use App\Models\PricingPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PricingFaqItem
 */
class PricingFaqItemResource extends JsonResource
{
    /**
     * @param  array<string, int|string>  $figures  PricingPage::figures()
     */
    public function __construct(PricingFaqItem $item, private readonly array $figures)
    {
        parent::__construct($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'question' => PricingPage::fillFigures($this->question, $this->figures),
            'answer' => PricingPage::fillFigures($this->answer, $this->figures),
        ];
    }
}
