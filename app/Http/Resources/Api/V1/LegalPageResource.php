<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LegalPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * @mixin LegalPage
 */
class LegalPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'format' => 'markdown',
            'effective_on' => $this->effective_on?->toDateString(),
        ];
    }

    /**
     * @return array{meta: array{locale: string, fallback: bool, available_locales: list<string>}}
     */
    public function with(Request $request): array
    {
        $locale = App::getLocale();

        return ['meta' => [
            'locale' => $locale,
            'fallback' => $this->resource->isMissingTextsIn($locale),
            'available_locales' => config('app.supported_locales'),
        ]];
    }
}
