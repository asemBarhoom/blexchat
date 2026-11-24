<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\User;
use App\Services\Auth\FrontendUrl;
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
        return response()->json(['data' => self::publicPages(Cache::get('channel-oauth:pages:'.$request->user()->id, []))]);
    }

    public function selectPage(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['state' => ['required', 'string'], 'page_id' => ['required', 'string']]);

        return $this->callback($request->merge(['platform' => 'messenger']), 'messenger', app(ChannelOAuthProvider::class));
    }

    #[OA\Get(path: '/api/v1/channels/oauth/status', summary: 'OAuth provider readiness', security: [['sanctum' => []]], tags: ['Channels'], responses: [new OA\Response(response: 200, description: 'Provider configuration status')])]
    public function status(): JsonResponse
    {
        $driver = self::driver();

        return response()->json(['data' => ['driver' => $driver, 'providers' => collect(['messenger', 'whatsapp', 'tiktok'])->mapWithKeys(fn (string $platform): array => [$platform => [
            'configured' => $driver !== 'provider' || ChannelOAuthProvider::configured($platform),
            'login_configured' => $driver !== 'provider' || ChannelOAuthProvider::credentialsConfigured($platform),
            'account_connection_available' => $driver !== 'provider' || ChannelOAuthProvider::configured($platform),
            'content_reading_available' => $driver !== 'provider' || ChannelOAuthProvider::contentReadingAvailable($platform),
            'messaging_available' => ChannelOAuthProvider::messagingAvailable($platform),
            'comment_automation_available' => ChannelOAuthProvider::commentAutomationAvailable($platform),
        ]])->all()]]);
    }

    #[OA\Get(path: '/api/v1/channels/oauth/{platform}/start', summary: 'Start a channel OAuth flow', tags: ['Channels'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['messenger', 'whatsapp', 'tiktok']))], responses: [new OA\Response(response: 200, description: 'OAuth state and authorization URL'), new OA\Response(response: 503, description: 'Provider credentials or messaging approval are pending')])]
    public function start(Request $request, string $platform, ChannelOAuthProvider $provider): JsonResponse
    {
        abort_unless(in_array($platform, ['messenger', 'whatsapp', 'tiktok'], true), 404);
        if (! $this->providerAvailable($platform)) {
            return $this->providerPending($platform);
        }
        $state = Str::random(40);
        Cache::put("channel-oauth:{$state}", [
            'user_id' => $request->user()->id,
            'workspace_id' => $request->user()->workspace()->id,
            'platform' => $platform,
            'frontend_url' => FrontendUrl::fromOrigin($request->header('Origin')),
        ], now()->addMinutes(10));

        $url = self::driver() === 'fake' ? url("/api/v1/channels/oauth/{$platform}/callback?state={$state}") : $provider->authorizationUrl($platform, $state);

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
            new OA\Response(response: 503, description: 'Provider credentials or messaging approval are pending'),
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

        if ($request->filled('page_id') && (int) $pending['user_id'] !== (int) $request->user()?->id) {
            return response()->json(['message' => __('channels.oauth_state_invalid'), 'code' => 'invalid_oauth_state'], 400);
        }

        $frontendUrl = $pending['frontend_url'] ?? rtrim((string) config('app.frontend_url'), '/');
        $user = User::findOrFail($pending['user_id']);
        $accountId = (string) ($request->input('page_id') ?: $request->input('phone_number_id') ?: $request->input('open_id'));
        $accessToken = (string) ($pending['access_token'] ?? $request->input('access_token', 'fake-oauth-token'));
        $pages = $pending['pages'] ?? null;
        $metaUserId = $pending['meta_user_id'] ?? null;
        $channelDetails = [];

        if ($pages === null && self::driver() === 'provider') {
            $result = $provider->exchange($platform, (string) $request->query('code'));
            $accessToken = $result['access_token'];
            $metaUserId = $result['meta_user_id'] ?? null;
            $channelDetails = array_filter([
                'username' => $result['username'] ?? null,
                'profile_picture' => $result['profile_picture'] ?? null,
                'refresh_token' => $result['refresh_token'] ?? null,
                'token_expires_at' => isset($result['expires_in']) ? now()->addSeconds((int) $result['expires_in']) : null,
            ]);
            $accountId = (string) ($accountId ?: ($result['phone_number_id'] ?? $result['account_id'] ?? ''));
            if ($platform === 'messenger' && $accountId === '' && count($result['pages'] ?? []) > 1) {
                // A code works once: keep its result with the state so the pick reuses it instead of asking Meta again.
                Cache::put("channel-oauth:{$state}", [...$pending, 'access_token' => $result['access_token'], 'pages' => $result['pages'], 'meta_user_id' => $metaUserId], now()->addMinutes(10));
                Cache::put('channel-oauth:pages:'.$user->id, $result['pages'], now()->addMinutes(10));

                if (! $request->expectsJson()) {
                    // Back from Meta in the browser: the site shows the Pages to pick from.
                    return redirect()->away($frontendUrl.'/onboarding/connect/messenger/pages?'.http_build_query(['state' => $state]));
                }

                return response()->json(['data' => ['pages' => self::publicPages($result['pages']), 'state' => $state]], 200);
            }
            $accountId = (string) ($accountId ?: data_get($result, 'pages.0.id'));
            $pages = $result['pages'] ?? null;
        }

        if ($pages !== null && $accountId !== '') {
            // A Page has its own token; the account (user) token does not send its messages.
            $page = collect($pages)->firstWhere('id', $accountId);

            if ($platform === 'messenger' && $page === null) {
                return response()->json(['message' => __('channels.oauth_no_account'), 'code' => 'account_ineligible'], 422);
            }

            $accessToken = (string) ($page['access_token'] ?? $accessToken);

            // The Page's name is what the app shows for a Messenger channel.
            if (filled($page['name'] ?? null)) {
                $channelDetails['username'] ??= (string) $page['name'];
            }
        }

        if ($accountId === '' && self::driver() === 'fake') {
            $accountId = 'fake-oauth-'.$platform.'-'.$user->id;
        }
        abort_if($accountId === '', 422, __('channels.oauth_no_account'));

        if (Channel::where('platform', $platform)->where('account_id', $accountId)->where('user_id', '!=', $user->id)->exists()) {
            return response()->json(['message' => __('channels.connect.account_taken'), 'code' => 'channel_taken'], 409);
        }

        if ($refusal = $user->channelLimitRefusal($platform, $accountId)) {
            return response()->json($refusal, 403);
        }

        if ($platform === 'messenger' && self::driver() === 'provider') {
            $provider->subscribeMessengerPage($accountId, $accessToken);
        }

        $channel = Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform, 'account_id' => $accountId],
            [...array_filter(['workspace_id' => $pending['workspace_id'] ?? null, 'meta_user_id' => $metaUserId]), ...$channelDetails, 'access_token' => $accessToken, 'status' => 'active', 'connected_at' => now()],
        );

        Cache::forget("channel-oauth:{$state}");
        Cache::forget('channel-oauth:pages:'.$user->id);

        if (filled(config('services.channels.oauth_redirect_url')) && ! $request->expectsJson()) {
            // The browser came back from the provider: on to the site's "connected" screen.
            return redirect()->away($frontendUrl."/onboarding/connected/{$platform}?".http_build_query(['id' => $channel->id]));
        }

        return response()->json(['data' => ['channel' => $channel->id, 'platform' => $platform, 'account_id' => $accountId]], 201);
    }

    private function providerAvailable(string $platform): bool
    {
        return (self::driver() === 'fake' && ! app()->isProduction())
            || (self::driver() === 'provider' && ChannelOAuthProvider::configured($platform) && filled(config('services.channels.oauth_redirect_url')));
    }

    private function providerPending(string $platform): JsonResponse
    {
        $provider = in_array($platform, ['messenger', 'whatsapp'], true) ? 'Meta' : 'TikTok';
        $channel = trans('channels.platforms.'.$platform);

        return response()->json(['message' => trans('channels.provider_pending', ['channel' => $channel, 'provider' => $provider]), 'code' => 'provider_pending', 'platform' => $platform], 503);
    }

    /**
     * The Pages as the browser may see them: each Page's own token stays on the server.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @return list<array{id: string, name: string}>
     */
    private static function publicPages(array $pages): array
    {
        return collect($pages)->map(fn (array $page): array => ['id' => (string) $page['id'], 'name' => (string) ($page['name'] ?? '')])->values()->all();
    }

    /**
     * The fake sign-in connects channels without the platform, so production always uses the real one.
     */
    private static function driver(): string
    {
        return app()->isProduction() ? 'provider' : (string) config('services.channels.oauth_driver');
    }
}
