<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

/**
 * Google sign-in (OAuth 2.0 authorization code flow). Google sends the visitor back to the
 * frontend's verify page, which posts the code here; only the verified email and name are read.
 */
class GoogleClient
{
    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * @return array{email: string, email_verified: bool, name?: string, sub: string}
     */
    public function profile(string $code): array
    {
        $token = Http::asForm()->connectTimeout(5)->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])
            ->throw()
            ->json('access_token');

        return Http::withToken($token)->connectTimeout(5)->timeout(15)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();
    }

    /**
     * The frontend's verify page, unless GOOGLE_REDIRECT_URI sends Google back to this API instead (testing without the frontend).
     * It must be listed exactly in the Google OAuth client's authorised redirect URIs.
     */
    private function redirectUri(): string
    {
        return config('services.google.redirect_uri') ?: rtrim(config('app.frontend_url'), '/').'/verify/confirm';
    }
}
