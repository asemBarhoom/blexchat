<?php

namespace App\Services\Channels;

use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ChannelOAuthProvider
{
    public static function configured(string $platform): bool
    {
        return filled(config("services.{$platform}.client_id"))
            && ($platform !== 'whatsapp' || filled(config('services.whatsapp.config_id')));
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

    /** @return array{access_token:string, account_id?:string, pages?:list<array<string,mixed>>, waba_id?:string, phone_number_id?:string} */
    public function exchange(string $platform, string $code): array
    {
        if ($platform === 'tiktok') {
            return Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => config('services.tiktok.client_id'), 'client_secret' => config('services.tiktok.client_secret'), 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => self::redirectUri($platform)])->throw()->json();
        }
        $token = $this->graph()->get('oauth/access_token', ['client_id' => config("services.{$platform}.client_id"), 'client_secret' => config("services.{$platform}.client_secret"), 'redirect_uri' => self::redirectUri($platform), 'code' => $code])->throw()->json('access_token');
        if ($platform === 'messenger') {
            $pages = $this->graph($token)->get('me/accounts', ['fields' => 'id,name,access_token'])->throw()->json('data', []);

            return ['access_token' => $token, 'pages' => $pages, 'meta_user_id' => $this->metaUserId($token)];
        }

        [$wabaId, $phoneNumberId] = $this->whatsappAccount($token);

        return ['access_token' => $token, 'waba_id' => $wabaId, 'phone_number_id' => $phoneNumberId, 'meta_user_id' => $this->metaUserId($token)];
    }

    /**
     * Install the app on the connected Page so Meta delivers Messenger messages and feed events.
     */
    public function subscribeMessengerPage(string $pageId, string $pageToken): void
    {
        Http::withToken($pageToken)
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://graph.facebook.com/'.config('services.meta.graph_version')."/{$pageId}/subscribed_apps", [
                'subscribed_fields' => 'messages,feed',
            ])
            ->throw();
    }

    /**
     * The person's app-scoped Facebook id, kept so Meta's deletion callbacks can find their channels.
     */
    private function metaUserId(string $token): ?string
    {
        return InstagramClient::metaUserId($this->graph($token));
    }

    /** @return array{0: ?string, 1: ?string} */
    private function whatsappAccount(string $token): array
    {
        $debug = $this->graph()->get('debug_token', [
            'input_token' => $token,
            'access_token' => config('services.meta.app_id').'|'.config('services.meta.app_secret'),
        ])->throw()->json();

        $wabaId = collect(data_get($debug, 'data.granular_scopes', []))
            ->firstWhere('scope', 'whatsapp_business_management')['target_ids'][0] ?? null;

        if (! is_scalar($wabaId) || $wabaId === '') {
            return [null, null];
        }

        $wabaId = (string) $wabaId;
        $phoneNumberId = $this->graph($token)->get("{$wabaId}/phone_numbers", [
            'fields' => 'id,display_phone_number,verified_name',
        ])->throw()->json('data.0.id');

        if (! is_scalar($phoneNumberId) || $phoneNumberId === '') {
            return [$wabaId, null];
        }

        $this->graph($token)->post("{$wabaId}/subscribed_apps")->throw();

        return [$wabaId, (string) $phoneNumberId];
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
