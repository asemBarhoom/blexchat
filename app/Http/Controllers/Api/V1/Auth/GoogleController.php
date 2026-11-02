<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithAuthErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\GoogleSignInRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Models\User;
use App\Services\Auth\GoogleClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use OpenApi\Attributes as OA;

class GoogleController extends Controller
{
    use RespondsWithAuthErrors;

    public function __construct(private GoogleClient $google) {}

    #[OA\Get(
        path: '/api/v1/auth/google',
        summary: 'Start signing up or in with Google',
        description: 'Send the visitor to `url`. Google returns them to `FRONTEND_URL/verify/confirm` with `code` and `state`; post both to **POST /api/v1/auth/google** within 15 minutes. If Google returns `error` instead, show "Google sign-in didn’t finish".',
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'Google consent URL', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'url', type: 'string', format: 'uri')]),
        ]))],
    )]
    public function redirect(): JsonResponse
    {
        $state = Crypt::encryptString(json_encode([
            'purpose' => 'google',
            'expires' => now()->addMinutes(15)->timestamp,
        ]));

        return response()->json(['data' => ['url' => $this->google->authorizationUrl($state)]]);
    }

    #[OA\Post(
        path: '/api/v1/auth/google',
        summary: 'Finish signing up or in with Google',
        description: 'Creates the account on first use with the Google email and name. Same answer as the email link: `is_new_user` decides onboarding or the app.',
        tags: ['Authentication'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response, and of the email where one is sent. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['code', 'state'], properties: [
            new OA\Property(property: 'code', type: 'string', description: 'The `code` query value Google sent back'),
            new OA\Property(property: 'state', type: 'string', description: 'The `state` query value Google sent back'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Signed in', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AuthSession'),
            ])),
            new OA\Response(response: 422, description: 'Missing fields, a stale `state` (validation error), or `google_failed`: Google refused the code or the email is not verified', content: new OA\JsonContent(ref: '#/components/schemas/AuthError')),
        ],
    )]
    public function signIn(GoogleSignInRequest $request): JsonResponse
    {
        try {
            $profile = $this->google->profile($request->string('code'));
        } catch (ConnectionException|RequestException) {
            $profile = null;
        }

        if (empty($profile['email']) || ($profile['email_verified'] ?? false) !== true) {
            return $this->authError(422, 'google_failed', __('auth.google_failed'));
        }

        [$user, $isNew] = User::signInWithEmail($profile['email'], $profile['name'] ?? null);
        $user->google_id ??= $profile['sub'];
        $user->save();

        return (new AuthSessionResource($user, $user->createToken('api')->plainTextToken, $isNew))->response()->setStatusCode(200);
    }
}
