<?php

use App\Models\Channel;
use App\Models\User;
use App\Services\Channels\ChannelOAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('connects a fake Messenger, WhatsApp or TikTok OAuth account', function (string $platform, string $accountKey) {
    config()->set('services.channels.oauth_driver', 'fake');
    $owner = User::factory()->create();
    $start = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/channels/oauth/{$platform}/start")->assertOk();
    $state = $start->json('data.state');

    $this->getJson("/api/v1/channels/oauth/{$platform}/callback?state={$state}&{$accountKey}=provider-account")
        ->assertCreated()
        ->assertJsonPath('data.platform', $platform);

    expect(Channel::where('user_id', $owner->id)->where('platform', $platform)->where('account_id', 'provider-account')->exists())->toBeTrue();
})->with([
    'messenger' => ['messenger', 'page_id'],
    'whatsapp' => ['whatsapp', 'phone_number_id'],
    'tiktok' => ['tiktok', 'open_id'],
]);

it('rejects an OAuth callback when the state is expired or reused', function () {
    config()->set('services.channels.oauth_driver', 'fake');
    $owner = User::factory()->create();
    $state = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/channels/oauth/messenger/start')->json('data.state');

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&page_id=page-1")->assertCreated();
    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&page_id=page-1")->assertBadRequest();
});

it('reports provider readiness from the configured credentials', function () {
    config()->set('services.channels.oauth_driver', 'provider');
    config()->set('services.messenger.client_id', null);
    config()->set('services.whatsapp.client_id', null);
    config()->set('services.whatsapp.config_id', null);
    config()->set('services.tiktok.client_id', 'tiktok-app-id');
    config()->set('services.tiktok.client_secret', 'tiktok-secret');
    config()->set('services.tiktok.auth_url', 'https://www.tiktok.com/v2/auth/authorize?client_key=tiktok-app-id&scope=biz.messages&redirect_uri=https%3A%2F%2Fapi.test%2Foauth%2Ftiktok');

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/status')
        ->assertOk()
        ->assertJsonPath('data.driver', 'provider')
        ->assertJsonPath('data.providers.messenger.configured', false)
        ->assertJsonPath('data.providers.whatsapp.configured', false)
        ->assertJsonPath('data.providers.tiktok.configured', true)
        ->assertJsonPath('data.providers.tiktok.login_configured', true)
        ->assertJsonPath('data.providers.tiktok.account_connection_available', true)
        ->assertJsonPath('data.providers.tiktok.content_reading_available', true)
        ->assertJsonPath('data.providers.tiktok.messaging_available', true)
        ->assertJsonPath('data.providers.tiktok.comment_automation_available', true)
        // TikTok's Comment-to-Message is not open in this region: comments get a public reply only.
        ->assertJsonPath('data.providers.tiktok.comment_dm_available', false);

    // TikTok for Business needs its authorization URL as well as the app's id and secret.
    config()->set('services.tiktok.auth_url', null);
    $this->getJson('/api/v1/channels/oauth/status')
        ->assertJsonPath('data.providers.tiktok.configured', false)
        ->assertJsonPath('data.providers.tiktok.messaging_available', false);
});

it('reports every provider ready on the fake driver', function () {
    config()->set('services.channels.oauth_driver', 'fake');
    config()->set('services.tiktok.client_id', null);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/status')
        ->assertOk()
        ->assertJsonPath('data.driver', 'fake')
        ->assertJsonPath('data.providers.messenger.configured', true)
        ->assertJsonPath('data.providers.whatsapp.configured', true)
        ->assertJsonPath('data.providers.tiktok.configured', true);
});

it('requires a WhatsApp Embedded Signup configuration on the provider driver', function () {
    config()->set('services.channels.oauth_driver', 'provider');
    config()->set('services.channels.oauth_redirect_url', 'https://api.test/oauth/whatsapp');
    config()->set('services.whatsapp.client_id', 'meta-app');
    config()->set('services.whatsapp.config_id', null);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/status')
        ->assertOk()
        ->assertJsonPath('data.providers.whatsapp.configured', false);
});

it('refuses to start provider OAuth until credentials are configured', function () {
    config()->set('services.channels.oauth_driver', 'provider');

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/messenger/start')
        ->assertStatus(503)->assertJsonPath('code', 'provider_pending');
});

it('starts TikTok for Business authorization with a state, asking again even after an earlier one', function () {
    config([
        'services.channels.oauth_driver' => 'provider',
        'services.channels.oauth_redirect_url' => 'https://api.test/oauth/{platform}',
        'services.tiktok.client_id' => 'tiktok-app-id',
        'services.tiktok.client_secret' => 'tiktok-secret',
        'services.tiktok.auth_url' => 'https://www.tiktok.com/v2/auth/authorize?client_key=tiktok-app-id&scope=biz.messages&redirect_uri=https%3A%2F%2Fapi.test%2Foauth%2Ftiktok',
    ]);

    $data = $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/tiktok/start')
        ->assertOk()
        ->assertJsonPath('data.platform', 'tiktok')
        ->json('data');

    expect($data['authorization_url'])->toBe('https://www.tiktok.com/v2/auth/authorize?client_key=tiktok-app-id&scope=biz.messages&redirect_uri=https%3A%2F%2Fapi.test%2Foauth%2Ftiktok&'.http_build_query(['state' => $data['state'], 'disable_auto_auth' => 1]));
});

describe('connecting a TikTok account through TikTok for Business', function () {
    beforeEach(function () {
        config([
            'services.channels.oauth_driver' => 'provider',
            'services.channels.oauth_redirect_url' => 'https://api.test/oauth/{platform}',
            'services.tiktok.client_id' => 'tiktok-app-id',
            'services.tiktok.client_secret' => 'tiktok-secret',
            'services.tiktok.auth_url' => 'https://www.tiktok.com/v2/auth/authorize?client_key=tiktok-app-id&scope=biz.messages&redirect_uri=https%3A%2F%2Fapi.test%2Foauth%2Ftiktok',
        ]);
        $this->profile = fn (bool $advanced): array => [
            'business-api.tiktok.com/open_api/v1.3/tt_user/oauth2/token/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['access_token' => 'tt-access', 'refresh_token' => 'tt-refresh', 'open_id' => 'tt-open-id', 'expires_in' => 86400]]),
            'business-api.tiktok.com/open_api/v1.3/business/get/*' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['username' => 'nourboutique', 'display_name' => 'Nour', 'profile_image' => 'https://cdn.example/avatar.jpg', 'has_advanced_access' => $advanced]]),
        ];
    });

    it('connects an account with Advanced Access, keeping its business id, profile and tokens', function () {
        Http::fake(($this->profile)(true));
        $owner = User::factory()->create();
        $state = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/channels/oauth/tiktok/start')->json('data.state');

        $this->getJson("/api/v1/channels/oauth/tiktok/callback?state={$state}&code=oauth-code")
            ->assertCreated()
            ->assertJsonPath('data.account_id', 'tt-open-id');

        expect(Channel::where('platform', 'tiktok')->sole())
            ->account_id->toBe('tt-open-id')
            ->username->toBe('nourboutique')
            ->profile_picture->toBe('https://cdn.example/avatar.jpg')
            ->refresh_token->toBe('tt-refresh')
            ->token_expires_at->not->toBeNull();
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'tt_user/oauth2/token/')
            && $request->data() === ['client_id' => 'tiktok-app-id', 'client_secret' => 'tiktok-secret', 'grant_type' => 'authorization_code', 'auth_code' => 'oauth-code', 'redirect_uri' => 'https://api.test/oauth/tiktok']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'business/get/') && $request->hasHeader('Access-Token', 'tt-access'));
    });

    it('refuses an account without Advanced Access, which TikTok keeps out of direct messages', function () {
        Http::fake(($this->profile)(false));
        $state = $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/channels/oauth/tiktok/start')->json('data.state');

        $this->getJson("/api/v1/channels/oauth/tiktok/callback?state={$state}&code=oauth-code")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'account_ineligible')
            ->assertJsonPath('reason', 'no_advanced_access');

        expect(Channel::where('platform', 'tiktok')->exists())->toBeFalse();
    });
});

it('never uses the fake channel sign-in in production', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('services.channels.oauth_driver', 'fake');
    config()->set('services.messenger.client_id', null);
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/channels/oauth/status')->assertJsonPath('data.driver', 'provider');
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/channels/oauth/messenger/start')->assertStatus(503);
});

it('asks Facebook for the Page permissions that reading and replying to comments need', function () {
    parse_str((string) parse_url(app(ChannelOAuthProvider::class)->authorizationUrl('messenger', 'state'), PHP_URL_QUERY), $query);

    expect(explode(',', $query['scope']))->toContain('pages_messaging', 'pages_manage_metadata', 'pages_read_engagement', 'pages_manage_engagement');
});

it('starts WhatsApp through its Facebook Login for Business configuration', function () {
    config([
        'services.whatsapp.client_id' => 'meta-app',
        'services.whatsapp.config_id' => 'wa-config',
        'services.channels.oauth_redirect_url' => 'https://api.test/oauth/{platform}',
    ]);

    parse_str((string) parse_url(app(ChannelOAuthProvider::class)->authorizationUrl('whatsapp', 'safe-state'), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'client_id' => 'meta-app',
        'redirect_uri' => 'https://api.test/oauth/whatsapp',
        'state' => 'safe-state',
        'config_id' => 'wa-config',
        'response_type' => 'code',
        'override_default_response_type' => '1',
    ])->not->toHaveKey('scope');
});

it('discovers and subscribes the WhatsApp number returned by Embedded Signup', function () {
    config([
        'services.meta.app_id' => 'meta-app',
        'services.meta.app_secret' => 'meta-secret',
        'services.meta.graph_version' => 'v21.0',
        'services.whatsapp.client_id' => 'meta-app',
        'services.whatsapp.client_secret' => 'meta-secret',
        'services.channels.oauth_redirect_url' => 'https://api.test/oauth/{platform}',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/v21.0/oauth/access_token*' => Http::response(['access_token' => 'business-token']),
        'graph.facebook.com/v21.0/debug_token*' => Http::response(['data' => ['granular_scopes' => [
            ['scope' => 'whatsapp_business_management', 'target_ids' => ['waba-1']],
        ]]]),
        'graph.facebook.com/v21.0/waba-1/phone_numbers*' => Http::response(['data' => [['id' => 'phone-1', 'display_phone_number' => '+970 59 000 0000']]]),
        'graph.facebook.com/v21.0/waba-1/subscribed_apps' => Http::response(['success' => true]),
        'graph.facebook.com/v21.0/me*' => Http::response(['id' => 'meta-user-1']),
    ]);

    $result = app(ChannelOAuthProvider::class)->exchange('whatsapp', 'one-time-code');

    expect($result)->toMatchArray([
        'access_token' => 'business-token',
        'waba_id' => 'waba-1',
        'phone_number_id' => 'phone-1',
        'username' => '+970 59 000 0000',
        'meta_user_id' => 'meta-user-1',
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v21.0/waba-1/subscribed_apps'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer business-token'));
});

it('connects a real WhatsApp account through Embedded Signup callback', function () {
    config([
        'services.channels.oauth_driver' => 'provider',
        'services.channels.oauth_redirect_url' => 'https://api.test/oauth/{platform}',
        'services.meta.app_id' => 'meta-app',
        'services.meta.app_secret' => 'meta-secret',
        'services.meta.graph_version' => 'v21.0',
        'services.whatsapp.client_id' => 'meta-app',
        'services.whatsapp.client_secret' => 'meta-secret',
        'services.whatsapp.config_id' => 'wa-config',
    ]);
    Http::fake([
        'graph.facebook.com/v21.0/oauth/access_token*' => Http::response(['access_token' => 'business-token']),
        'graph.facebook.com/v21.0/debug_token*' => Http::response(['data' => ['granular_scopes' => [
            ['scope' => 'whatsapp_business_management', 'target_ids' => ['waba-22']],
        ]]]),
        'graph.facebook.com/v21.0/waba-22/phone_numbers*' => Http::response(['data' => [['id' => 'phone-22', 'display_phone_number' => '+966 50 123 4567', 'verified_name' => 'Blexchat Store']]]),
        'graph.facebook.com/v21.0/waba-22/subscribed_apps' => Http::response(['success' => true]),
        'graph.facebook.com/v21.0/me*' => Http::response(['id' => 'meta-user-22']),
    ]);
    $owner = User::factory()->create();
    $state = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/channels/oauth/whatsapp/start')->json('data.state');

    $this->getJson("/api/v1/channels/oauth/whatsapp/callback?state={$state}&code=oauth-code")
        ->assertCreated()
        ->assertJsonPath('data.account_id', 'phone-22');

    $channel = Channel::where('platform', 'whatsapp')->sole();
    expect($channel->account_id)->toBe('phone-22')
        ->and($channel->username)->toBe('Blexchat Store')
        ->and($channel->status)->toBe('active')
        ->and($channel->meta_user_id)->toBe('meta-user-22');
});
