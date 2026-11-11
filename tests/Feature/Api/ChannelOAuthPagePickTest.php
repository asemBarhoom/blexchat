<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.channels.oauth_driver', 'provider');
    config()->set('services.messenger.client_id', 'app-id');
    config()->set('services.messenger.client_secret', 'app-secret');
    config()->set('services.channels.oauth_redirect_url', 'https://front.test/oauth');

    $this->pages = [
        ['id' => 'page-1', 'name' => 'One', 'access_token' => 'page-token-1'],
        ['id' => 'page-2', 'name' => 'Two', 'access_token' => 'page-token-2'],
    ];

    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/*/me/accounts*' => fn () => Http::response(['data' => $this->pages]),
    ]);

    /** @return array{0: User, 1: string} */
    $this->startMessenger = function (): array {
        $owner = User::factory()->create();

        $state = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/channels/oauth/messenger/start')
            ->assertOk()
            ->json('data.state');

        return [$owner, $state];
    };
});

it('returns the Pages and connects the chosen one to the started workspace', function () {
    [$owner, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")
        ->assertOk()
        ->assertJsonPath('data.state', $state)
        ->assertJsonCount(2, 'data.pages')
        ->assertJsonPath('data.pages.1.id', 'page-2');

    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-2'])
        ->assertRedirect();

    $channel = Channel::where('platform', 'messenger')->where('account_id', 'page-2')->sole();

    expect($channel->user_id)->toBe($owner->id)
        ->and($channel->workspace_id)->toBe($owner->workspace()->id)
        ->and($channel->access_token)->toBe('page-token-2');
});

it('does not let the state be used twice after the Page is picked', function () {
    [$owner, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertOk();
    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-2'])->assertRedirect();

    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-1'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'invalid_oauth_state');

    expect(Channel::where('user_id', $owner->id)->count())->toBe(1);
});

it('connects straight away when there is a single Page', function () {
    $this->pages = [['id' => 'only-page', 'name' => 'Only', 'access_token' => 'only-page-token']];

    [, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")
        ->assertRedirect();

    expect(Channel::where('account_id', 'only-page')->sole()->access_token)->toBe('only-page-token');
});

it('answers both errors in Arabic with their codes', function () {
    $arabic = require lang_path('ar/channels.php');

    $this->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/channels/oauth/messenger/callback?state=unknown')
        ->assertStatus(400)
        ->assertJsonPath('code', 'invalid_oauth_state')
        ->assertJsonPath('message', $arabic['oauth_state_invalid']);

    config()->set('services.channels.oauth_driver', 'fake');
    $owner = User::factory()->create();
    $state = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/channels/oauth/messenger/start')->json('data.state');
    Channel::factory()->create(['platform' => 'messenger', 'account_id' => 'taken-page']);

    $this->withHeader('Accept-Language', 'ar')
        ->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&page_id=taken-page")
        ->assertStatus(409)
        ->assertJsonPath('code', 'channel_taken')
        ->assertJsonPath('message', $arabic['connect']['account_taken']);
});
