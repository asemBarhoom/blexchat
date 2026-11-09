<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChannelHealthController extends Controller
{
    #[OA\Get(path: '/api/v1/channels/{channel}/health', summary: 'Check a connected channel readiness', security: [['sanctum' => []]], tags: ['Channels'], parameters: [new OA\Parameter(name: 'channel', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Readiness state')])]
    public function __invoke(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($channel->user_id === $request->user()->id, 403);
        $ready = $channel->status === 'active' && filled($channel->access_token) && (! $channel->token_expires_at || $channel->token_expires_at->isFuture());

        return response()->json(['data' => ['channel_id' => $channel->id, 'platform' => $channel->platform, 'status' => $channel->status, 'ready' => $ready, 'token_expires_at' => $channel->token_expires_at?->toIso8601String(), 'send_supported' => in_array($channel->platform, ['instagram', 'whatsapp', 'messenger'], true)]]);
    }
}
