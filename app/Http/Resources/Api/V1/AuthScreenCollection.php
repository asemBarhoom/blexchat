<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\App;

/**
 * The screens keyed by their key, so the frontend reads `data.signup.title`.
 */
class AuthScreenCollection extends ResourceCollection
{
    public $collects = AuthScreenResource::class;

    /**
     * @return array<string, AuthScreenResource>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->mapWithKeys(fn (AuthScreenResource $screen): array => [$screen->key => $screen])->all();
    }

    /**
     * @return array{meta: array{locale: string, fallback: bool, available_locales: list<string>}}
     */
    public function with(Request $request): array
    {
        $locale = App::getLocale();

        return ['meta' => [
            'locale' => $locale,
            'fallback' => $this->collection->contains(fn (AuthScreenResource $screen): bool => $screen->isMissingTextsIn($locale)),
            'available_locales' => config('app.supported_locales'),
        ]];
    }
}
