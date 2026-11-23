<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
    $this->pageSubscriptions = Http::sequence()->whenEmpty(Http::response(['success' => true]));

    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/*/me/accounts*' => fn () => Http::response(['data' => $this->pages]),
        'graph.facebook.com/*/me?*' => Http::response(['id' => 'fb-person-1']),
        'graph.facebook.com/*/*/subscribed_apps' => $this->pageSubscriptions,
    ]);

    /** @return array{0: User, 1: string} */
    $this->startMessenger = function (?string $origin = null): array {
        $owner = User::factory()->create();

        $request = $this->actingAs($owner, 'sanctum');
        if ($origin !== null) {
            $request->withHeader('Origin', $origin);
        }

        $state = $request
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
        ->assertJsonPath('data.pages.1.id', 'page-2')
        ->assertJsonMissingPath('data.pages.0.access_token');

    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-2'])
        ->assertCreated();

    $channel = Channel::where('platform', 'messenger')->where('account_id', 'page-2')->sole();

    expect($channel->user_id)->toBe($owner->id)
        ->and($channel->workspace_id)->toBe($owner->workspace()->id)
        ->and($channel->access_token)->toBe('page-token-2');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/page-2/subscribed_apps')
        && $request->hasHeader('Authorization', 'Bearer page-token-2')
        && $request['subscribed_fields'] === 'messages,messaging_postbacks,feed');
});

it('refuses a Page that was not returned by Meta', function () {
    [$owner, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertOk();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'unknown-page'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'account_ineligible');

    expect(Channel::where('user_id', $owner->id)->exists())->toBeFalse();
});

it('does not let another signed-in user finish a Page selection', function () {
    [, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertOk();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-1'])
        ->assertBadRequest()
        ->assertJsonPath('code', 'invalid_oauth_state');

    expect(Channel::count())->toBe(0);
});

it('does not save the channel when Meta refuses the Page subscription', function () {
    [$owner, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertOk();
    $this->pageSubscriptions->push(['error' => ['message' => 'Missing permission']], 400);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-1'])
        ->assertServerError();

    expect(Channel::where('user_id', $owner->id)->exists())->toBeFalse();
});

it('does not let the state be used twice after the Page is picked', function () {
    [$owner, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertOk();
    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-2'])->assertCreated();

    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-1'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'invalid_oauth_state');

    expect(Channel::where('user_id', $owner->id)->count())->toBe(1);
});

it('connects straight away when there is a single Page', function () {
    $this->pages = [['id' => 'only-page', 'name' => 'Only', 'access_token' => 'only-page-token']];
    config()->set('app.frontend_url', 'https://site.test');

    [, $state] = ($this->startMessenger)();

    // The browser comes back from Meta, so it goes on to the site's "connected" screen.
    $this->get("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")
        ->assertRedirect('https://site.test/onboarding/connected/messenger?id='.Channel::where('account_id', 'only-page')->value('id'));

    expect(Channel::where('account_id', 'only-page')->sole()->access_token)->toBe('only-page-token');
});

it('returns channel OAuth to the allowed frontend that started it', function () {
    config([
        'app.frontend_url' => 'https://client.example.com',
        'cors.allowed_origins' => ['https://client.example.com'],
        'cors.allowed_origins_patterns' => ['#^https://blexchat-screens\.vercel\.app$#'],
    ]);
    $this->pages = [['id' => 'only-page', 'name' => 'Only', 'access_token' => 'only-page-token']];

    [, $allowedState] = ($this->startMessenger)('https://blexchat-screens.vercel.app');
    $this->get("/api/v1/channels/oauth/messenger/callback?state={$allowedState}&code=code-1")
        ->assertRedirect('https://blexchat-screens.vercel.app/onboarding/connected/messenger?id='.Channel::where('account_id', 'only-page')->value('id'));

    $this->pages = [['id' => 'second-page', 'name' => 'Second', 'access_token' => 'second-page-token']];
    [, $untrustedState] = ($this->startMessenger)('https://evil.example.com');
    $this->get("/api/v1/channels/oauth/messenger/callback?state={$untrustedState}&code=code-2")
        ->assertRedirect('https://client.example.com/onboarding/connected/messenger?id='.Channel::where('account_id', 'second-page')->value('id'));
});

it('finds the Page shared through a business portfolio when Meta lists no Pages for the person', function () {
    $this->pages = [];
    Http::fake([
        'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['granular_scopes' => [
            ['scope' => 'pages_messaging', 'target_ids' => ['business-page']],
            ['scope' => 'pages_show_list', 'target_ids' => ['business-page']],
        ]]]),
        'graph.facebook.com/*/business-page?*' => Http::response(['id' => 'business-page', 'name' => 'Business', 'access_token' => 'business-page-token']),
    ]);

    [, $state] = ($this->startMessenger)();

    config()->set('app.frontend_url', 'https://site.test');

    $this->get("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertRedirect();

    expect(Channel::where('account_id', 'business-page')->sole()->access_token)->toBe('business-page-token');
});

it('sends Meta the callback of the platform being connected', function () {
    config()->set('services.channels.oauth_redirect_url', 'https://api.test/api/v1/channels/oauth/{platform}/callback');

    $url = $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/channels/oauth/messenger/start')
        ->assertOk()
        ->json('data.authorization_url');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://api.test/api/v1/channels/oauth/messenger/callback');
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

it('keeps the person’s Facebook id on the connected Page for Meta’s deletion callbacks', function () {
    [$owner, $state] = ($this->startMessenger)();

    $this->getJson("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")->assertOk();
    $this->actingAs($owner, 'sanctum')->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-2']);

    expect(Channel::where('account_id', 'page-2')->sole()->meta_user_id)->toBe('fb-person-1');
});

it('sends the browser to the site to pick a Page, and lets it pick in production', function () {
    config()->set('app.frontend_url', 'https://site.test');
    [$owner, $state] = ($this->startMessenger)();
    app()->detectEnvironment(fn (): string => 'production');

    $this->get("/api/v1/channels/oauth/messenger/callback?state={$state}&code=code-1")
        ->assertRedirect("https://site.test/onboarding/connect/messenger/pages?state={$state}");

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/channels/oauth/messenger/pages')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1', ['id' => 'page-2', 'name' => 'Two']);

    $this->postJson('/api/v1/channels/oauth/messenger/pages', ['state' => $state, 'page_id' => 'page-1'])
        ->assertCreated();

    expect(Channel::where('account_id', 'page-1')->sole()->user_id)->toBe($owner->id);
});
