<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithAuthErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\SendLoginLinkRequest;
use App\Http\Requests\Api\Auth\VerifyLoginLinkRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Mail\LoginLinkMail;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use OpenApi\Attributes as OA;

class LoginLinkController extends Controller
{
    use RespondsWithAuthErrors;

    /** Links one address may be sent per window, so the endpoint cannot be used to flood an inbox. */
    private const MAX_LINKS = 5;

    private const WINDOW_MINUTES = 15;

    #[OA\Post(
        path: '/api/v1/auth/email-link',
        summary: 'Email a sign-in link (sign up and sign in)',
        description: <<<'MD'
            Sign-up and sign-in are passwordless: this emails a link to the address, whether or not it has an account yet.
            The link opens `FRONTEND_URL/verify/confirm?token=…` (with `&mode=signin` when signing in); that page posts the token to **POST /api/v1/auth/email-link/verify**.

            - A link works **once, for 15 minutes**.
            - Another link to the same address can be sent after **59 seconds** (`resend_after`); earlier, the answer is 429 `resend_too_soon` with `retry_after`.
            - After 5 links in 15 minutes the answer is 429 `too_many_attempts` ("Too many attempts. Try again in a few minutes.").
            - `mode` only changes the email's wording: `signup` (default) "Confirm your email", `signin` "Your sign-in link".

            **Development servers only:** with `AUTH_EXPOSE_LOGIN_LINKS=true` the response also carries `dev_link` and `dev_token`, so the flow can be tested without an inbox. Never present in production.
            MD,
        tags: ['Authentication'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response, and of the email where one is sent. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email'], properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'sara@nourboutique.com'),
            new OA\Property(property: 'mode', type: 'string', enum: ['signup', 'signin'], default: 'signup'),
        ])),
        responses: [
            new OA\Response(response: 202, description: 'Link sent', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Check your email.'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'sara@nourboutique.com'),
                    new OA\Property(property: 'mode', type: 'string', enum: ['signup', 'signin']),
                    new OA\Property(property: 'expires_in', type: 'integer', description: 'Seconds the link works for', example: 900),
                    new OA\Property(property: 'resend_after', type: 'integer', description: 'Seconds before another link can be sent', example: 59),
                    new OA\Property(property: 'dev_link', type: 'string', format: 'uri', description: 'Development servers only: the emailed link'),
                    new OA\Property(property: 'dev_token', type: 'string', description: 'Development servers only: the token to post to /email-link/verify'),
                ]),
            ])),
            new OA\Response(response: 422, description: 'Invalid email or mode', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: '`resend_too_soon` or `too_many_attempts`', content: new OA\JsonContent(ref: '#/components/schemas/AuthError')),
        ],
    )]
    public function send(SendLoginLinkRequest $request): JsonResponse
    {
        $email = $request->email();
        $limiterKey = 'login-link:'.$email;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_LINKS)) {
            return $this->authError(429, 'too_many_attempts', __('auth.link.too_many'), ['retry_after' => RateLimiter::availableIn($limiterKey)]);
        }

        $wait = LoginLink::secondsUntilResend($email);

        if ($wait > 0) {
            return $this->authError(429, 'resend_too_soon', __('auth.link.resend_wait', ['seconds' => $wait]), ['retry_after' => $wait]);
        }

        RateLimiter::hit($limiterKey, self::WINDOW_MINUTES * 60);

        [$link, $token] = LoginLink::issue($email, $request->mode());
        Mail::to($email)->locale(App::getLocale())->send(new LoginLinkMail($link, $token));

        return response()->json([
            'message' => __('auth.link.sent'),
            'data' => [
                'email' => $email,
                'mode' => $link->mode,
                'expires_in' => LoginLink::LIFETIME_MINUTES * 60,
                'resend_after' => LoginLink::RESEND_AFTER_SECONDS,
                ...config('auth.expose_login_links') ? ['dev_link' => $link->url($token), 'dev_token' => $token] : [],
            ],
        ], 202);
    }

    #[OA\Post(
        path: '/api/v1/auth/email-link/verify',
        summary: 'Sign in with the token from the emailed link',
        description: <<<'MD'
            Called by the frontend's verify page ("One more step") with the `token` from its URL.
            Creates the account on first use, and returns the bearer token for every later request (`Authorization: Bearer …`).

            - `is_new_user: true` → a new account: go to onboarding ("Account created").
            - `is_new_user: false` → a returning user: go to the app ("Welcome back").
            - 410 `link_expired` → the link was used already, is past its 15 minutes, or is unknown. `data.email` (when known) is the address to offer "Send a new link" to.
            MD,
        tags: ['Authentication'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response, and of the email where one is sent. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token'], properties: [
            new OA\Property(property: 'token', type: 'string', description: 'The `token` query value of the emailed link'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Signed in', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AuthSession'),
            ])),
            new OA\Response(response: 410, description: '`link_expired`', content: new OA\JsonContent(allOf: [
                new OA\Schema(ref: '#/components/schemas/AuthError'),
                new OA\Schema(properties: [new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'mode', type: 'string', enum: ['signup', 'signin'], nullable: true),
                ])]),
            ])),
            new OA\Response(response: 422, description: 'Token missing', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function verify(VerifyLoginLinkRequest $request): JsonResponse
    {
        $link = LoginLink::findByToken($request->string('token'));

        // Marked used in one conditional update, so two tabs opening the same link cannot both sign in.
        $claimed = $link?->isUsable() && LoginLink::whereKey($link->id)->whereNull('used_at')->update(['used_at' => now()]) === 1;

        if (! $claimed) {
            return $this->authError(410, 'link_expired', __('auth.link.expired', ['minutes' => LoginLink::LIFETIME_MINUTES]), [
                'data' => ['email' => $link?->email, 'mode' => $link?->mode],
            ]);
        }

        [$user, $isNew] = User::signInWithEmail($link->email);

        return (new AuthSessionResource($user, $user->createToken('api')->plainTextToken, $isNew))->response()->setStatusCode(200);
    }
}
