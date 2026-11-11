<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\ConnectInstagramRequest;
use App\Http\Resources\Api\V1\ChannelResource;
use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'List, connect and disconnect social channels')]
#[OA\Schema(schema: 'ConnectError', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', description: 'Ready to show, in the requested language'),
    new OA\Property(property: 'code', type: 'string', enum: ['account_taken', 'access_denied', 'account_ineligible', 'provider_error'], description: 'Stable value to branch on; picks the W06 state (taken, denied, ineligible)'),
    new OA\Property(property: 'account', type: 'object', nullable: true, description: 'The account the sign-in returned (account_taken, account_ineligible)', properties: [
        new OA\Property(property: 'platform', type: 'string', example: 'instagram'),
        new OA\Property(property: 'handle', type: 'string', example: 'nourboutique'),
        new OA\Property(property: 'type', type: 'string', nullable: true, example: 'PERSONAL', description: 'What the provider says the account is'),
    ]),
    new OA\Property(property: 'access_request_token', type: 'string', nullable: true, description: 'account_taken only: send it to POST /api/v1/channels/access-requests ("Request access") within 60 minutes'),
    new OA\Property(property: 'error', type: 'string', nullable: true, description: 'provider_error only: the provider’s own reason'),
])]
class InstagramConnectionController extends Controller
{
    /** What Instagram calls the professional accounts that can connect. */
    private const ELIGIBLE_ACCOUNT_TYPES = ['BUSINESS', 'MEDIA_CREATOR'];

    public function __construct(private InstagramClient $instagram) {}

    #[OA\Get(
        path: '/api/v1/channels/instagram/authorize',
        summary: 'Start connecting an Instagram professional account',
        description: 'Send the owner to `url`. Instagram returns them to INSTAGRAM_REDIRECT_URI with `code` and `state`; post both to POST /api/v1/channels/instagram within 15 minutes.',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        responses: [new OA\Response(response: 200, description: 'Consent URL', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'url', type: 'string', format: 'uri')]),
        ]))]
    )]
    public function authorize(Request $request): JsonResponse
    {
        $state = Crypt::encryptString(json_encode([
            'user' => $request->user()->id,
            'expires' => now()->addMinutes(15)->timestamp,
        ]));

        return response()->json(['data' => ['url' => $this->instagram->authorizationUrl($state)]]);
    }

    #[OA\Post(
        path: '/api/v1/channels/instagram',
        summary: 'Finish connecting an Instagram professional account',
        description: <<<'MD'
            Post what Instagram sent back to the redirect page. Exchanges the code for a long-lived token, saves the account and subscribes it to comments and messages.

            - **Reconnect** (W06 `reconnect`): signing in again to an account this owner already has refreshes its token and makes it `active`, so its paused automations run again. `meta.reconnected` is true.
            - **`access_denied`** (422): Instagram sent `error` instead of `code`: the owner cancelled or refused (W06 `denied`).
            - **`account_ineligible`** (422): Instagram says the account is not a professional one (W06 `ineligible`).
            - **`account_taken`** (409): another workspace already connected this account (W06 `taken`). Send `access_request_token` for "Request access".
            MD,
        security: [['sanctum' => []]],
        tags: ['Channels'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the error messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['state'], properties: [
            new OA\Property(property: 'code', type: 'string', description: 'Required unless `error` is sent'),
            new OA\Property(property: 'state', type: 'string'),
            new OA\Property(property: 'error', type: 'string', nullable: true, example: 'access_denied', description: 'As Instagram sent it, when the owner cancelled or refused'),
            new OA\Property(property: 'error_reason', type: 'string', nullable: true, example: 'user_denied'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Channel connected, or reconnected', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object'),
                new OA\Property(property: 'meta', type: 'object', properties: [new OA\Property(property: 'reconnected', type: 'boolean')]),
            ])),
            new OA\Response(response: 403, description: '`code: channel_limit` with `limit`: the plan’s channels are all in use'),
            new OA\Response(response: 409, description: '`account_taken`', content: new OA\JsonContent(ref: '#/components/schemas/ConnectError')),
            new OA\Response(response: 422, description: '`access_denied`, `account_ineligible`, or an invalid or expired state', content: new OA\JsonContent(ref: '#/components/schemas/ConnectError')),
            new OA\Response(response: 502, description: '`provider_error`: Instagram refused the connection', content: new OA\JsonContent(ref: '#/components/schemas/ConnectError')),
        ]
    )]
    public function store(ConnectInstagramRequest $request): JsonResponse
    {
        if ($request->wasDenied()) {
            return $this->connectError(422, 'access_denied');
        }

        try {
            $token = $this->instagram->exchangeCode($request->string('code'));
            $profile = $this->instagram->profile($token['access_token']);
        } catch (RequestException $exception) {
            return $this->providerError($exception);
        }

        $account = ['platform' => 'instagram', 'handle' => $profile['username'], 'type' => $profile['account_type'] ?? null];

        // Refused only when Instagram says so: an account it does not type is let through.
        if (isset($profile['account_type']) && ! in_array(strtoupper($profile['account_type']), self::ELIGIBLE_ACCOUNT_TYPES, true)) {
            return $this->connectError(422, 'account_ineligible', ['account' => $account]);
        }

        $takenBy = Channel::query()
            ->where('platform', 'instagram')
            ->where('account_id', (string) $profile['user_id'])
            ->where('user_id', '!=', $request->user()->id)
            ->first();

        if ($takenBy !== null) {
            return $this->connectError(409, 'account_taken', [
                'account' => $account,
                'access_request_token' => ChannelAccessRequest::tokenFor($request->user(), $takenBy),
            ]);
        }

        if ($refusal = $request->user()->channelLimitRefusal('instagram', (string) $profile['user_id'])) {
            return response()->json($refusal, 403);
        }

        try {
            $this->instagram->subscribeToWebhooks($token['access_token']);
        } catch (RequestException $exception) {
            return $this->providerError($exception);
        }

        $channel = $request->user()->channels()->updateOrCreate(
            ['platform' => 'instagram', 'account_id' => (string) $profile['user_id']],
            [
                'workspace_id' => $request->user()->workspace()->id,
                'username' => $profile['username'],
                'profile_picture' => $profile['profile_picture_url'] ?? null,
                'followers' => $profile['followers_count'] ?? 0,
                'access_token' => $token['access_token'],
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'status' => 'active',
                'connected_at' => now(),
            ],
        );

        return response()->json(['data' => new ChannelResource($channel), 'meta' => ['reconnected' => ! $channel->wasRecentlyCreated]], 201);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function connectError(int $status, string $code, array $extra = []): JsonResponse
    {
        return response()->json(['message' => __("channels.connect.{$code}", ['channel' => 'Instagram']), 'code' => $code, ...$extra], $status);
    }

    private function providerError(RequestException $exception): JsonResponse
    {
        return $this->connectError(502, 'provider_error', ['error' => InstagramClient::errorMessage($exception)]);
    }
}
