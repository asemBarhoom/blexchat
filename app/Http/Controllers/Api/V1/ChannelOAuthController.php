<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\User;
use App\Services\Channels\ChannelOAuthProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'List, connect and disconnect social channels')]
class ChannelOAuthController extends Controller
{
    public function pages(Request $request): JsonResponse
    {
        abort_unless(! app()->isProduction(), 501);

        return response()->json(['data' => ['pages' => Cache::get('channel-oauth:pages:'.$request->user()->id, [])]]);
    }

    public function selectPage(Request $request): JsonResponse
    {
        abort_unless(! app()->isProduction(), 501);
        $request->validate(['state' => ['required', 'string'], 'page_id' => ['required', 'string']]);

        return $this->callback($request->merge(['platform' => 'messenger']), 'messenger', app(ChannelOAuthProvider::class));
    }

    #[OA\Get(path: '/api/v1/channels/oauth/status', summary: 'OAuth provider readiness', security: [['sanctum' => []]], tags: ['Channels'], responses: [new OA\Response(response: 200, description: 'Provider configuration status')])]
    public function status(): JsonResponse
    {
        $driver = (string) config('services.channels.oauth_driver');

        return response()->json(['data' => ['driver' => $driver, 'providers' => collect(['messenger', 'whatsapp', 'tiktok'])->mapWithKeys(fn (string $platform): array => [$platform => ['configured' => $driver !== 'provider' || filled(config("services.{$platform}.client_id"))]])->all()]]);
    }

    #[OA\Get(path: '/api/v1/channels/oauth/{platform}/start', summary: 'Start a channel OAuth flow', tags: ['Channels'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['messenger', 'whatsapp', 'tiktok']))], responses: [new OA\Response(response: 200, description: 'OAuth state and authorization URL'), new OA\Response(response: 501, description: 'Provider OAuth is not configured')])]
    public function start(Request $request, string $platform, ChannelOAuthProvider $provider): JsonResponse
    {
        abort_unless((config('services.channels.oauth_driver') === 'fake' && ! app()->isProduction()) || (filled(config("services.{$platform}.client_id")) && filled(config('services.channels.oauth_redirect_url'))), 501, __('channels.oauth_not_configured'));
        abort_unless(in_array($platform, ['messenger', 'whatsapp', 'tiktok'], true), 404);
        $state = Str::random(40);
        Cache::put("channel-oauth:{$state}", ['user_id' => $request->user()->id, 'platform' => $platform], now()->addMinutes(10));

        $url = config('services.channels.oauth_driver') === 'fake' ? url("/api/v1/channels/oauth/{$platform}/callback?state={$state}") : $provider->authorizationUrl($platform, $state);

        return response()->json(['data' => ['platform' => $platform, 'state' => $state, 'authorization_url' => $url]]);
    }

    #[OA\Get(path: '/api/v1/channels/oauth/{platform}/callback', summary: 'Complete a channel OAuth flow', tags: ['Channels'], parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['messenger', 'whatsapp', 'tiktok'])), new OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Channel connected'), new OA\Response(response: 400, description: 'Invalid or expired OAuth state'), new OA\Response(response: 501, description: 'Provider OAuth is not configured')])]
    public function callback(Request $request, string $platform, ChannelOAuthProvider $provider): JsonResponse
    {
        abort_unless((config('services.channels.oauth_driver') === 'fake' && ! app()->isProduction()) || filled(config("services.{$platform}.client_id")), 501, __('channels.oauth_not_configured'));
        $state = (string) $request->query('state');
        $pending = Cache::pull("channel-oauth:{$state}");
        abort_unless(is_array($pending) && $pending['platform'] === $platform, 400, 'Invalid OAuth state.');

        $user = User::findOrFail($pending['user_id']);
        $accountId = (string) ($request->input('page_id') ?: $request->input('phone_number_id') ?: $request->input('open_id'));
        $accessToken = (string) $request->input('access_token', 'fake-oauth-token');
        if (config('services.channels.oauth_driver') === 'provider') {
            $result = $provider->exchange($platform, (string) $request->query('code'));
            $accessToken = $result['access_token'];
            $accountId = (string) ($accountId ?: ($result['phone_number_id'] ?? $result['account_id'] ?? ''));
            if ($platform === 'messenger' && $accountId === '' && count($result['pages'] ?? []) > 1) {
                Cache::put('channel-oauth:pages:'.$user->id, $result['pages'], now()->addMinutes(10));

                return response()->json(['data' => ['pages' => $result['pages'], 'state' => $state]], 200);
            }
            $accountId = (string) ($accountId ?: data_get($result, 'pages.0.id'));
        }
        if ($accountId === '' && config('services.channels.oauth_driver') === 'fake') {
            $accountId = 'fake-oauth-'.$platform.'-'.$user->id;
        }
        abort_if($accountId === '', 422, __('channels.oauth_no_account'));
        abort_if(Channel::where('platform', $platform)->where('account_id', $accountId)->where('user_id', '!=', $user->id)->exists(), 409, 'This account is already connected to another workspace.');

        $channel = Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform, 'account_id' => $accountId],
            ['access_token' => $accessToken, 'status' => 'active', 'connected_at' => now()],
        );

        $redirect = config('services.channels.oauth_redirect_url');
        if (filled($redirect)) {
            return redirect()->away($redirect.'?'.http_build_query(['platform' => $platform, 'id' => $channel->id]));
        }

        return response()->json(['data' => ['channel' => $channel->id, 'platform' => $platform, 'account_id' => $accountId]], 201);
    }
}
