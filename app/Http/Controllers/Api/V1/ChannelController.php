<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\ConnectChannelRequest;
use App\Http\Requests\Api\Channel\ListChannelsRequest;
use App\Http\Resources\Api\V1\ChannelResource;
use App\Http\Resources\Api\V1\ConnectedChannelResource;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'List, connect and disconnect social channels')]
class ChannelController extends Controller
{
    #[OA\Get(
        path: '/api/v1/channels',
        summary: 'The connected channels, for the sidebar and the channels page',
        description: 'Every channel of the signed-in owner, oldest connection first. Each item has the fields of GET /api/v1/channels/{channel} without `screen` and `next_step`. A channel whose `status` is not `active` has dropped and needs reconnecting.',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        responses: [
            new OA\Response(response: 200, description: 'The channels; an empty list before the first one connects', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 12),
                    new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
                    new OA\Property(property: 'account_id', type: 'string', example: '17841400000'),
                    new OA\Property(property: 'username', type: 'string', nullable: true, example: '@nourboutique'),
                    new OA\Property(property: 'profile_picture', type: 'string', format: 'uri', nullable: true),
                    new OA\Property(property: 'followers', type: 'integer', example: 12400),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'expired']),
                    new OA\Property(property: 'connected_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'token_expires_at', type: 'string', format: 'date-time', nullable: true),
                ])),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function index(ListChannelsRequest $request): AnonymousResourceCollection
    {
        $channels = $request->user()->workspace()->channels()->oldest('connected_at')->oldest('id')->get();

        return ConnectedChannelResource::collection(
            $channels->map(fn (Channel $channel): ConnectedChannelResource => new ConnectedChannelResource($channel, forScreen: false)),
        );
    }

    #[OA\Post(
        path: '/api/v1/channels/connect',
        summary: 'Connect a new social channel',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['platform', 'access_token', 'account_id'],
                properties: [
                    new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
                    new OA\Property(property: 'access_token', type: 'string', example: 'EAATjTKF4xxx'),
                    new OA\Property(property: 'account_id', type: 'string', example: '17841400000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Channel connected'),
            new OA\Response(response: 403, description: '`code: channel_limit` with `limit` when the plan’s channels, every workspace together, are all in use (show Upgrade)'),
            new OA\Response(response: 409, description: 'Already connected'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function connect(ConnectChannelRequest $request): JsonResponse
    {
        $exists = $request->user()->workspace()->channels()
            ->where('platform', $request->platform)
            ->where('account_id', $request->account_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => __('channels.already_connected')], 409);
        }

        if ($refusal = $request->user()->channelLimitRefusal($request->platform, $request->account_id)) {
            return response()->json($refusal, 403);
        }

        $channel = $request->user()->channels()->create([
            'workspace_id' => $request->user()->workspace()->id,
            'platform' => $request->platform,
            'access_token' => $request->access_token,
            'account_id' => $request->account_id,
            'status' => 'active',
            'connected_at' => now(),
        ]);

        return response()->json(['data' => new ChannelResource($channel)], 201);
    }

    #[OA\Delete(
        path: '/api/v1/channels/{channelId}',
        summary: 'Disconnect a channel',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        parameters: [new OA\Parameter(name: 'channelId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Disconnected'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($request->user()->workspace()->holds($channel), 403);

        $channel->delete();

        return response()->json(['message' => __('channels.disconnected')]);
    }
}
