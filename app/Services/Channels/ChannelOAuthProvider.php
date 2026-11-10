<?php

namespace App\Services\Channels;

use Illuminate\Support\Facades\Http;

class ChannelOAuthProvider
{
    public function authorizationUrl(string $platform, string $state): string
    {
        $redirect = (string) config('services.channels.oauth_redirect_url');
        if ($platform === 'tiktok') {
            return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query(['client_key' => config('services.tiktok.client_id'), 'scope' => 'user.info.basic,video.list', 'response_type' => 'code', 'redirect_uri' => $redirect, 'state' => $state]);
        }
        $scope = $platform === 'whatsapp' ? 'business_management,whatsapp_business_management,whatsapp_business_messaging' : 'pages_show_list,pages_manage_metadata,pages_messaging';

        return 'https://www.facebook.com/dialog/oauth?'.http_build_query(['client_id' => config("services.{$platform}.client_id"), 'redirect_uri' => $redirect, 'state' => $state, 'scope' => $scope]);
    }

    /** @return array{access_token:string, account_id?:string, pages?:list<array<string,mixed>>, waba_id?:string, phone_number_id?:string} */
    public function exchange(string $platform, string $code): array
    {
        if ($platform === 'tiktok') {
            return Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => config('services.tiktok.client_id'), 'client_secret' => config('services.tiktok.client_secret'), 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => config('services.channels.oauth_redirect_url')])->throw()->json();
        }
        $token = Http::get('https://graph.facebook.com/'.config('services.meta.graph_version').'/oauth/access_token', ['client_id' => config("services.{$platform}.client_id"), 'client_secret' => config("services.{$platform}.client_secret"), 'redirect_uri' => config('services.channels.oauth_redirect_url'), 'code' => $code])->throw()->json('access_token');
        if ($platform === 'messenger') {
            $pages = Http::withToken($token)->get('https://graph.facebook.com/'.config('services.meta.graph_version').'/me/accounts', ['fields' => 'id,name,access_token'])->throw()->json('data', []);

            return ['access_token' => $token, 'pages' => $pages];
        }

        return ['access_token' => $token, 'waba_id' => null, 'phone_number_id' => null];
    }
}
