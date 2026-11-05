<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use App\Services\Instagram\FakeInstagramClient;
use App\Services\Instagram\InstagramClient;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.instagram.driver' => 'fake', 'app.frontend_url' => 'https://app.test', 'services.instagram.redirect_uri' => null]);
    Http::preventStrayRequests();
    $this->seed(OnboardingSeeder::class);
    $this->owner = User::factory()->create();
});

/**
 * Starts the sign-in like the frontend does and opens the signed test page it gets back.
 */
function fakeSignInPage(User $owner): string
{
    return test()->actingAs($owner)->getJson('/api/v1/channels/instagram/authorize')->assertOk()->json('data.url');
}

/**
 * Answers the test page and returns the query the frontend's return page receives.
 *
 * @return array<string, string>
 */
function answerFakeSignIn(string $pageUrl, string $answer): array
{
    $location = test()->post($pageUrl, ['answer' => $answer])
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)->toStartWith('https://app.test/onboarding/connect?');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);

    return $query;
}

it('swaps in the fake client only when it is chosen and allowed', function () {
    expect(app(InstagramClient::class))->toBeInstanceOf(FakeInstagramClient::class);

    config(['services.instagram.driver' => 'meta']);
    expect(app(InstagramClient::class))->not->toBeInstanceOf(FakeInstagramClient::class);

    config(['services.instagram.driver' => 'fake']);
    app()->detectEnvironment(fn () => 'production');
    expect(app(InstagramClient::class))->not->toBeInstanceOf(FakeInstagramClient::class);

    config(['services.instagram.allow_fake' => true]);
    expect(app(InstagramClient::class))->toBeInstanceOf(FakeInstagramClient::class);
});

it('connects a test Instagram account through the test sign-in page, with no network', function () {
    $page = fakeSignInPage($this->owner);
    $this->get($page)->assertOk()->assertSee('@test_shop_'.$this->owner->id);

    $answer = answerFakeSignIn($page, 'business');

    $this->actingAs($this->owner)->postJson('/api/v1/channels/instagram', $answer)
        ->assertCreated()
        ->assertJsonPath('data.platform', 'instagram')
        ->assertJsonPath('meta.reconnected', false);

    $this->getJson('/api/v1/onboarding')->assertJsonPath('data.next_step', 'survey');

    $channel = $this->owner->channels()->sole();
    $this->getJson("/api/v1/channels/{$channel->id}/posts")->assertOk()->assertJsonCount(4, 'data');
});

it('answers like Instagram when the owner cancels or picks a personal account', function (string $answer, string $code) {
    $query = answerFakeSignIn(fakeSignInPage($this->owner), $answer);

    $this->actingAs($this->owner)->postJson('/api/v1/channels/instagram', $query)
        ->assertUnprocessable()
        ->assertJsonPath('code', $code);

    expect($this->owner->channels()->exists())->toBeFalse();
})->with([
    'cancel' => ['deny', 'access_denied'],
    'personal account' => ['personal', 'account_ineligible'],
]);

it('refuses the test page without a valid signature, or when the fake client is off', function () {
    $page = fakeSignInPage($this->owner);

    $this->get(preg_replace('/signature=[^&]+/', 'signature=forged', $page))->assertForbidden();

    config(['services.instagram.driver' => 'meta']);
    $this->get($page)->assertNotFound();
});

it('runs a simulated comment through the matching automation and fills the inbox', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    $automation = Automation::factory()->for($this->owner)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [
            ['type' => 'reply_comment', 'message' => 'Sent you the details 💌'],
            ['type' => 'send_dm', 'message' => 'The abaya is 249 SAR'],
        ],
    ]);

    $response = $this->actingAs($this->owner)->postJson("/api/v1/channels/{$channel->id}/test-events", [
        'type' => 'comment', 'text' => 'what is the price?', 'from' => 'lulu.styles',
    ])->assertCreated()->assertJsonPath('data.automation_id', $automation->id);

    $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.id', $response->json('data.conversation_id'));
    $this->getJson('/api/v1/contacts')->assertOk()->assertJsonCount(1, 'data');
});

it('opens a DM thread from a simulated message', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);

    $conversation = $this->actingAs($this->owner)->postJson("/api/v1/channels/{$channel->id}/test-events", [
        'type' => 'message', 'text' => 'Hi, is the black kaftan in stock?', 'from' => 'lulu.styles',
    ])->assertCreated()->json('data.conversation_id');

    $this->getJson("/api/v1/conversations/{$conversation}/messages")->assertOk()
        ->assertJsonFragment(['content' => 'Hi, is the black kaftan in stock?']);
});

it('keeps simulated events to the owner, to valid input, and to the fake client', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'status' => 'active']);
    $event = ['type' => 'message', 'text' => 'hello'];

    $this->actingAs(User::factory()->create())->postJson("/api/v1/channels/{$channel->id}/test-events", $event)->assertForbidden();
    $this->actingAs($this->owner)->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'like'])
        ->assertUnprocessable()->assertJsonValidationErrors(['type', 'text']);

    config(['services.instagram.driver' => 'meta']);
    $this->postJson("/api/v1/channels/{$channel->id}/test-events", $event)->assertNotFound();
});

it('names only the automation that answered this message, not an earlier reply in the thread', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    $automation = Automation::factory()->for($this->owner)->for($channel)->create(['trigger_type' => 'dm_keyword', 'keywords' => ['deliver'], 'actions' => [['type' => 'send_dm', 'message' => 'Yes, 2–4 days']]]);
    $event = fn (string $text) => $this->actingAs($this->owner)->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'message', 'text' => $text, 'from' => 'faisal.a']);

    $event('do you deliver?')->assertJsonPath('data.automation_id', $automation->id);
    $event('thanks')->assertJsonPath('data.automation_id', null);

    $automation->moveToTrash();
    $event('deliver to Dubai?')->assertJsonPath('data.automation_id', null);
});

it('leaves someone new unanswered in DMs while new conversations are paused, but still records them', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    Automation::factory()->for($this->owner)->for($channel)->create(['trigger_type' => 'dm_keyword', 'keywords' => [], 'actions' => [['type' => 'send_dm', 'message' => 'Welcome!']]]);
    Automation::factory()->for($this->owner)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'reply_comment', 'message' => 'Check your DMs'], ['type' => 'send_dm', 'message' => '249 SAR']],
    ]);
    $this->owner->update(['inbox_accepting_new' => false]);

    $this->actingAs($this->owner)->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'message', 'text' => 'hi', 'from' => 'new.customer'])
        ->assertCreated()
        ->assertJsonPath('data.automation_id', null);

    $this->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'comment', 'text' => 'price?', 'from' => 'another.one'])
        ->assertCreated()
        ->assertJsonPath('data.conversation_id', null);

    $this->getJson('/api/v1/conversations')->assertJsonCount(1, 'data')->assertJsonPath('data.0.unread', 1);
});
