<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AuthScreen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * @mixin AuthScreen
 */
class AuthScreenResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return $this->textsIn(App::getLocale());
    }
}
