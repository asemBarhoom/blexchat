<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\ConnectInstagramRequest;
use App\Http\Resources\Api\V1\ChannelResource;
use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'Connect and disconnect social channels (the connected list comes with GET /api/v1/dashboard)')]
class InstagramConnectionController extends Controller
{
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
        description: 'Exchanges the code for a long-lived token, saves the account and subscribes it to comments and messages. Reconnecting an account refreshes its token.',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['code', 'state'], properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'state', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Channel connected'),
            new OA\Response(response: 422, description: 'Invalid or expired state'),
            new OA\Response(response: 502, description: 'Instagram refused the connection'),
        ]
    )]
    public function store(ConnectInstagramRequest $request): JsonResponse
    {
        try {
            $token = $this->instagram->exchangeCode($request->string('code'));
            $profile = $this->instagram->profile($token['access_token']);
            $this->instagram->subscribeToWebhooks($token['access_token']);
        } catch (RequestException $exception) {
            return response()->json([
                'message' => 'Instagram refused the connection.',
                'error' => $exception->response->json('error.message'),
            ], 502);
        }

        $channel = $request->user()->channels()->updateOrCreate(
            ['platform' => 'instagram', 'account_id' => (string) $profile['user_id']],
            [
                'username' => $profile['username'],
                'profile_picture' => $profile['profile_picture_url'] ?? null,
                'followers' => $profile['followers_count'] ?? 0,
                'access_token' => $token['access_token'],
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'status' => 'active',
                'connected_at' => now(),
            ],
        );

        return response()->json(['data' => new ChannelResource($channel)], 201);
    }
}
