<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\User;
use App\Services\Channels\ChannelOAuthProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Channels', description: 'List, connect and disconnect social channels')]
class ChannelOAuthController extends Controller
{
    public function pages(Request $request): JsonResponse
    {
        if (app()->isProduction()) {
            return $this->providerPending('messenger');
        }

        return response()->json(['data' => ['pages' => Cache::get('channel-oauth:pages:'.$request->user()->id, [])]]);
    }

    public function selectPage(Request $request): JsonResponse|RedirectResponse
    {
        if (app()->isProduction()) {
            return $this->providerPending('messenger');
        }
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
        abort_unless(in_array($platform, ['messenger', 'whatsapp', 'tiktok'], true), 404);
        if (! $this->providerAvailable($platform)) {
            return $this->providerPending($platform);
        }
        $state = Str::random(40);
        Cache::put("channel-oauth:{$state}", ['user_id' => $request->user()->id, 'workspace_id' => $request->user()->workspace()->id, 'platform' => $platform], now()->addMinutes(10));

        $url = config('services.channels.oauth_driver') === 'fake' ? url("/api/v1/channels/oauth/{$platform}/callback?state={$state}") : $provider->authorizationUrl($platform, $state);

        return response()->json(['data' => ['platform' => $platform, 'state' => $state, 'authorization_url' => $url]]);
    }

    #[OA\Get(
        path: '/api/v1/channels/oauth/{platform}/callback',
        summary: 'Complete a channel OAuth flow',
        tags: ['Channels'],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['messenger', 'whatsapp', 'tiktok'])),
            new OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'More than one Page: `data.pages` and `state`, then POST /api/v1/channels/oauth/messenger/pages with the chosen `page_id`'),
            new OA\Response(response: 201, description: 'Channel connected'),
            new OA\Response(response: 400, description: '`code: invalid_oauth_state` — unknown, expired or already used'),
            new OA\Response(response: 403, description: '`code: channel_limit` with `limit`: the plan’s channels are all in use'),
            new OA\Response(response: 409, description: '`code: channel_taken` — the account is connected to another workspace'),
            new OA\Response(response: 501, description: 'Provider OAuth is not configured'),
        ]
    )]
    public function callback(Request $request, string $platform, ChannelOAuthProvider $provider): JsonResponse|RedirectResponse
    {
        if (! $this->providerAvailable($platform)) {
            return $this->providerPending($platform);
        }
        $state = (string) $request->input('state');
        // The state stays until the flow finishes: picking a Page is a second request with the same state.
        $pending = Cache::get("channel-oauth:{$state}");

        if (! is_array($pending) || $pending['platform'] !== $platform) {
            return response()->json(['message' => __('channels.oauth_state_invalid'), 'code' => 'invalid_oauth_state'], 400);
        }

        $user = User::findOrFail($pending['user_id']);
        $accountId = (string) ($request->input('page_id') ?: $request->input('phone_number_id') ?: $request->input('open_id'));
        $accessToken = (string) ($pending['access_token'] ?? $request->input('access_token', 'fake-oauth-token'));
        $pages = $pending['pages'] ?? null;

        if ($pages === null && config('services.channels.oauth_driver') === 'provider') {
            $result = $provider->exchange($platform, (string) $request->query('code'));
            $accessToken = $result['access_token'];
            $accountId = (string) ($accountId ?: ($result['phone_number_id'] ?? $result['account_id'] ?? ''));
            if ($platform === 'messenger' && $accountId === '' && count($result['pages'] ?? []) > 1) {
                // A code works once: keep its result with the state so the pick reuses it instead of asking Meta again.
                Cache::put("channel-oauth:{$state}", [...$pending, 'access_token' => $result['access_token'], 'pages' => $result['pages']], now()->addMinutes(10));
                Cache::put('channel-oauth:pages:'.$user->id, $result['pages'], now()->addMinutes(10));

                return response()->json(['data' => ['pages' => $result['pages'], 'state' => $state]], 200);
            }
            $accountId = (string) ($accountId ?: data_get($result, 'pages.0.id'));
            $pages = $result['pages'] ?? null;
        }

        if ($pages !== null && $accountId !== '') {
            // A Page has its own token; the account (user) token does not send its messages.
            $page = collect($pages)->firstWhere('id', $accountId);
            $accessToken = (string) ($page['access_token'] ?? $accessToken);
        }

        if ($accountId === '' && config('services.channels.oauth_driver') === 'fake') {
            $accountId = 'fake-oauth-'.$platform.'-'.$user->id;
        }
        abort_if($accountId === '', 422, __('channels.oauth_no_account'));

        if (Channel::where('platform', $platform)->where('account_id', $accountId)->where('user_id', '!=', $user->id)->exists()) {
            return response()->json(['message' => __('channels.connect.account_taken'), 'code' => 'channel_taken'], 409);
        }

        if ($refusal = $user->channelLimitRefusal($platform, $accountId)) {
            return response()->json($refusal, 403);
        }

        $channel = Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform, 'account_id' => $accountId],
            [...array_filter(['workspace_id' => $pending['workspace_id'] ?? null]), 'access_token' => $accessToken, 'status' => 'active', 'connected_at' => now()],
        );

        Cache::forget("channel-oauth:{$state}");
        Cache::forget('channel-oauth:pages:'.$user->id);

        $redirect = config('services.channels.oauth_redirect_url');
        if (filled($redirect)) {
            return redirect()->away($redirect.'?'.http_build_query(['platform' => $platform, 'id' => $channel->id]));
        }

        return response()->json(['data' => ['channel' => $channel->id, 'platform' => $platform, 'account_id' => $accountId]], 201);
    }

    private function providerAvailable(string $platform): bool
    {
        return (config('services.channels.oauth_driver') === 'fake' && ! app()->isProduction())
            || (config('services.channels.oauth_driver') === 'provider' && filled(config("services.{$platform}.client_id")) && filled(config('services.channels.oauth_redirect_url')));
    }

    private function providerPending(string $platform): JsonResponse
    {
        $provider = in_array($platform, ['messenger', 'whatsapp'], true) ? 'Meta' : 'TikTok';
        $channel = trans('channels.platforms.'.$platform);

        return response()->json(['message' => trans('channels.provider_pending', ['channel' => $channel, 'provider' => $provider]), 'code' => 'provider_pending', 'platform' => $platform], 503);
    }
}
