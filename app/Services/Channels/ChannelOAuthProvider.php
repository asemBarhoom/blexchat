<?php

namespace App\Services\Channels;

use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ChannelOAuthProvider
{
    public static function configured(string $platform): bool
    {
        return self::credentialsConfigured($platform);
    }

    public static function credentialsConfigured(string $platform): bool
    {
        return filled(config("services.{$platform}.client_id"))
            && ($platform !== 'tiktok' || filled(config('services.tiktok.client_secret')))
            && ($platform !== 'whatsapp' || filled(config('services.whatsapp.config_id')));
    }

    /**
     * TikTok Login Kit can connect an account, but it does not grant the Messaging or Comment APIs.
     * Change this only with the real send, comment and webhook implementation after TikTok approval.
     */
    public static function messagingAvailable(string $platform): bool
    {
        return $platform !== 'tiktok';
    }

    public static function contentReadingAvailable(string $platform): bool
    {
        return $platform === 'instagram' || ($platform === 'tiktok' && self::credentialsConfigured($platform));
    }

    public static function commentAutomationAvailable(string $platform): bool
    {
        return $platform !== 'tiktok';
    }

    /**
     * Where the provider sends the person back with the code: the API's callback for this platform.
     * CHANNEL_OAUTH_REDIRECT_URL may hold a `{platform}` placeholder, filled here.
     */
    public static function redirectUri(string $platform): string
    {
        return str_replace('{platform}', $platform, (string) config('services.channels.oauth_redirect_url'));
    }

    public function authorizationUrl(string $platform, string $state): string
    {
        $redirect = self::redirectUri($platform);
        if ($platform === 'tiktok') {
            return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query(['client_key' => config('services.tiktok.client_id'), 'scope' => 'user.info.basic,video.list', 'response_type' => 'code', 'redirect_uri' => $redirect, 'state' => $state]);
        }
        if ($platform === 'whatsapp') {
            return 'https://www.facebook.com/dialog/oauth?'.http_build_query([
                'client_id' => config('services.whatsapp.client_id'),
                'redirect_uri' => $redirect,
                'state' => $state,
                'config_id' => config('services.whatsapp.config_id'),
                'response_type' => 'code',
                'override_default_response_type' => true,
            ]);
        }

        return 'https://www.facebook.com/dialog/oauth?'.http_build_query(['client_id' => config("services.{$platform}.client_id"), 'redirect_uri' => $redirect, 'state' => $state, 'scope' => 'pages_show_list,pages_manage_metadata,pages_messaging,pages_read_engagement,pages_manage_engagement']);
    }

    /** @return array{access_token:string, refresh_token?:string, account_id?:string, username?:string, profile_picture?:string, expires_in?:int, pages?:list<array<string,mixed>>, waba_id?:string, phone_number_id?:string} */
    public function exchange(string $platform, string $code): array
    {
        if ($platform === 'tiktok') {
            $token = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => config('services.tiktok.client_id'), 'client_secret' => config('services.tiktok.client_secret'), 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => self::redirectUri($platform)])->throw()->json();
            $profile = rescue(
                fn (): array => Http::withToken($token['access_token'])->get('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => 'open_id,display_name,avatar_url',
                ])->throw()->json('data.user', []),
                [],
                report: false,
            );

            return [
                ...$token,
                'account_id' => (string) ($profile['open_id'] ?? $token['open_id'] ?? ''),
                'username' => $profile['display_name'] ?? null,
                'profile_picture' => $profile['avatar_url'] ?? null,
            ];
        }
        $token = $this->graph()->get('oauth/access_token', ['client_id' => config("services.{$platform}.client_id"), 'client_secret' => config("services.{$platform}.client_secret"), 'redirect_uri' => self::redirectUri($platform), 'code' => $code])->throw()->json('access_token');
        if ($platform === 'messenger') {
            $pages = $this->graph($token)->get('me/accounts', ['fields' => 'id,name,access_token'])->throw()->json('data', []);

            return ['access_token' => $token, 'pages' => $pages ?: $this->grantedPages($token), 'meta_user_id' => $this->metaUserId($token)];
        }

        [$wabaId, $phoneNumberId, $username] = $this->whatsappAccount($token);

        return ['access_token' => $token, 'waba_id' => $wabaId, 'phone_number_id' => $phoneNumberId, 'username' => $username, 'meta_user_id' => $this->metaUserId($token)];
    }

    /**
     * Install the app on the connected Page so Meta delivers messages, postbacks and feed events.
     */
    public function subscribeMessengerPage(string $pageId, string $pageToken): void
    {
        Http::withToken($pageToken)
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://graph.facebook.com/'.config('services.meta.graph_version')."/{$pageId}/subscribed_apps", [
                'subscribed_fields' => 'messages,messaging_postbacks,feed',
            ])
            ->throw();
    }

    /**
     * The Pages the person picked in Meta's dialog, read from the token's own grants. `me/accounts`
     * leaves out Pages the person reaches through a business portfolio, so it can come back empty
     * even though a Page was shared; each granted Page is then asked for its own name and token.
     *
     * @return list<array{id: string, name: string, access_token: string}>
     */
    private function grantedPages(string $token): array
    {
        $scopes = rescue(fn () => $this->graph()->get('debug_token', [
            'input_token' => $token,
            'access_token' => config('services.meta.app_id').'|'.config('services.meta.app_secret'),
        ])->throw()->json('data.granular_scopes'), [], report: false) ?? [];

        return collect($scopes)
            ->filter(fn ($scope): bool => is_array($scope) && in_array($scope['scope'] ?? null, ['pages_messaging', 'pages_show_list'], true))
            ->flatMap(fn (array $scope): array => (array) ($scope['target_ids'] ?? []))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->map(fn (string $id): ?array => rescue(fn () => $this->graph($token)->get($id, ['fields' => 'id,name,access_token'])->throw()->json(), null, report: false))
            ->filter(fn ($page): bool => is_array($page) && filled($page['id'] ?? null) && filled($page['access_token'] ?? null))
            ->values()
            ->all();
    }

    /**
     * The person's app-scoped Facebook id, kept so Meta's deletion callbacks can find their channels.
     */
    private function metaUserId(string $token): ?string
    {
        return InstagramClient::metaUserId($this->graph($token));
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function whatsappAccount(string $token): array
    {
        $debug = $this->graph()->get('debug_token', [
            'input_token' => $token,
            'access_token' => config('services.meta.app_id').'|'.config('services.meta.app_secret'),
        ])->throw()->json();

        $wabaId = collect(data_get($debug, 'data.granular_scopes', []))
            ->firstWhere('scope', 'whatsapp_business_management')['target_ids'][0] ?? null;

        if (! is_scalar($wabaId) || $wabaId === '') {
            return [null, null, null];
        }

        $wabaId = (string) $wabaId;
        $phoneData = $this->graph($token)->get("{$wabaId}/phone_numbers", [
            'fields' => 'id,display_phone_number,verified_name',
        ])->throw()->json('data.0', []);

        $phoneNumberId = $phoneData['id'] ?? null;
        $username = $phoneData['verified_name'] ?? $phoneData['display_phone_number'] ?? null;

        if (! is_scalar($phoneNumberId) || $phoneNumberId === '') {
            return [$wabaId, null, null];
        }

        $this->graph($token)->post("{$wabaId}/subscribed_apps")->throw();

        return [$wabaId, (string) $phoneNumberId, is_string($username) && $username !== '' ? $username : null];
    }

    private function graph(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl('https://graph.facebook.com/'.config('services.meta.graph_version').'/')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);

        return filled($token) ? $request->withToken($token) : $request;
    }
}
