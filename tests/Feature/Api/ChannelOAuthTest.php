<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    config()->set('services.tiktok.client_id', 'tiktok-app-id');

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/status')
        ->assertOk()
        ->assertJsonPath('data.driver', 'provider')
        ->assertJsonPath('data.providers.messenger.configured', false)
        ->assertJsonPath('data.providers.whatsapp.configured', false)
        ->assertJsonPath('data.providers.tiktok.configured', true);
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

it('refuses to start provider OAuth until it is implemented', function () {
    config()->set('services.channels.oauth_driver', 'provider');

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/messenger/start')
        ->assertStatus(501);
});
