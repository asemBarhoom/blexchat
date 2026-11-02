<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signed-in session: the account and the bearer token for every later request.
 * `is_new_user` tells the frontend where to go next: onboarding for a new account, the app otherwise.
 */
class AuthSessionResource extends JsonResource
{
    public function __construct(User $user, private string $accessToken, private bool $isNewUser)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource),
            'access_token' => $this->accessToken,
            'token_type' => 'Bearer',
            'is_new_user' => $this->isNewUser,
        ];
    }
}
