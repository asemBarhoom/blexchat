<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\ConnectChannelRequest;
use App\Http\Resources\Api\V1\ChannelCollection;
use App\Http\Resources\Api\V1\ChannelResource;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'Connect and manage social media channels')]
class ChannelController extends Controller
{
    #[OA\Get(
        path: '/api/v1/channels',
        summary: 'List all connected channels',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        responses: [new OA\Response(response: 200, description: 'Channel list')]
    )]
    public function index(Request $request): ChannelCollection
    {
        $channels = $request->user()->channels()->get();

        return new ChannelCollection($channels);
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
            new OA\Response(response: 409, description: 'Already connected'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function connect(ConnectChannelRequest $request): JsonResponse
    {
        $exists = $request->user()->channels()
            ->where('platform', $request->platform)
            ->where('account_id', $request->account_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This account is already connected.'], 409);
        }

        $channel = $request->user()->channels()->create([
            'platform'     => $request->platform,
            'access_token' => $request->access_token,
            'account_id'   => $request->account_id,
            'status'       => 'active',
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
        abort_unless($channel->user_id === $request->user()->id, 403);

        $channel->delete();

        return response()->json(['message' => 'Channel disconnected successfully.']);
    }
}
