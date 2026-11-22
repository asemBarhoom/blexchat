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

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/status')
        ->assertOk()
        ->assertJsonPath('data.driver', 'provider')
        ->assertJsonPath('data.providers.messenger.configured', false)
        ->assertJsonPath('data.providers.whatsapp.configured', false)
        ->assertJsonPath('data.providers.tiktok.configured', false)
        ->assertJsonPath('data.providers.tiktok.login_configured', true)
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

it('keeps TikTok pending when Login Kit is configured but messaging is not available', function () {
    config([
        'services.channels.oauth_driver' => 'provider',
        'services.channels.oauth_redirect_url' => 'https://api.test/oauth/{platform}',
        'services.tiktok.client_id' => 'tiktok-app-id',
        'services.tiktok.client_secret' => 'tiktok-secret',
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/tiktok/start')
        ->assertStatus(503)
        ->assertJsonPath('code', 'provider_pending')
        ->assertJsonPath('platform', 'tiktok');
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
        'meta_user_id' => 'meta-user-1',
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v21.0/waba-1/subscribed_apps'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer business-token'));
});
