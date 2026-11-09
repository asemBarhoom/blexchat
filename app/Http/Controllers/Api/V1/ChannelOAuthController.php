<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'List, connect and disconnect social channels')]
class ChannelOAuthController extends Controller
{
    #[OA\Get(path: '/api/v1/channels/oauth/status', summary: 'OAuth provider readiness', security: [['sanctum' => []]], tags: ['Channels'], responses: [new OA\Response(response: 200, description: 'Provider configuration status')])]
    public function status(): JsonResponse
    {
        $driver = (string) config('services.channels.oauth_driver');

        return response()->json(['data' => ['driver' => $driver, 'providers' => collect(['messenger', 'whatsapp', 'tiktok'])->mapWithKeys(fn (string $platform): array => [$platform => ['configured' => $driver !== 'provider' || filled(config("services.{$platform}.client_id"))]])->all()]]);
    }

    #[OA\Get(path: '/api/v1/channels/oauth/{platform}/start', summary: 'Start a channel OAuth flow', tags: ['Channels'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['messenger', 'whatsapp', 'tiktok']))], responses: [new OA\Response(response: 200, description: 'OAuth state and authorization URL'), new OA\Response(response: 501, description: 'Provider OAuth is not configured')])]
    public function start(Request $request, string $platform): JsonResponse
    {
        abort_unless(config('services.channels.oauth_driver') === 'fake', 501, 'OAuth providers are not configured.');
        abort_unless(in_array($platform, ['messenger', 'whatsapp', 'tiktok'], true), 404);
        $state = Str::random(40);
        Cache::put("channel-oauth:{$state}", ['user_id' => $request->user()->id, 'platform' => $platform], now()->addMinutes(10));

        return response()->json(['data' => ['platform' => $platform, 'state' => $state, 'authorization_url' => url("/api/v1/channels/oauth/{$platform}/callback?state={$state}")]]);
    }

    #[OA\Get(path: '/api/v1/channels/oauth/{platform}/callback', summary: 'Complete a channel OAuth flow', tags: ['Channels'], parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['messenger', 'whatsapp', 'tiktok'])), new OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Channel connected'), new OA\Response(response: 400, description: 'Invalid or expired OAuth state'), new OA\Response(response: 501, description: 'Provider OAuth is not configured')])]
    public function callback(Request $request, string $platform): JsonResponse
    {
        abort_unless(config('services.channels.oauth_driver') === 'fake', 501, 'OAuth providers are not configured.');
        $state = (string) $request->query('state');
        $pending = Cache::pull("channel-oauth:{$state}");
        abort_unless(is_array($pending) && $pending['platform'] === $platform, 400, 'Invalid OAuth state.');

        $user = User::findOrFail($pending['user_id']);
        $accountId = (string) ($request->input('page_id') ?: $request->input('phone_number_id') ?: $request->input('open_id'));
        abort_if($accountId === '', 422, 'The provider did not return an account id.');

        $channel = Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform, 'account_id' => $accountId],
            ['access_token' => (string) $request->input('access_token', 'fake-oauth-token'), 'status' => 'active', 'connected_at' => now()],
        );

        return response()->json(['data' => ['channel' => $channel->id, 'platform' => $platform, 'account_id' => $accountId]], 201);
    }
}
