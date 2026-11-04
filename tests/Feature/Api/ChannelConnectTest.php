<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use App\Models\ChannelConnectScreen;
use App\Models\User;
use Database\Seeders\ChannelConnectScreenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.instagram.app_id' => 'app-123',
        'services.instagram.app_secret' => 'secret-xyz',
        'services.instagram.redirect_uri' => 'https://app.blexchat.test/connect/instagram',
        'services.instagram.graph_version' => 'v21.0',
    ]);

    $this->seed(ChannelConnectScreenSeeder::class);
    Sanctum::actingAs($this->user = User::factory()->create());
});

/**
 * Instagram answering a sign-in with this profile.
 *
 * @param  array<string, mixed>  $profile
 */
function fakeInstagramSignIn(array $profile = []): void
{
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-lived', 'user_id' => 123]),
        'graph.instagram.com/access_token*' => Http::response(['access_token' => 'IGAA-new', 'token_type' => 'bearer', 'expires_in' => 5184000]),
        'graph.instagram.com/v21.0/me/subscribed_apps*' => Http::response(['success' => true]),
        'graph.instagram.com/v21.0/me*' => Http::response([
            'user_id' => '17840001', 'username' => 'nourboutique', 'account_type' => 'BUSINESS', 'followers_count' => 14800, ...$profile,
        ]),
    ]);
}

function instagramState(User $user): string
{
    return Crypt::encryptString(json_encode(['user' => $user->id, 'expires' => now()->addMinutes(10)->timestamp]));
}

describe('GET /api/v1/onboarding/channels/{platform}/connect', function () {
    it('serves the screen copy for each channel', function () {
        $this->getJson('/api/v1/onboarding/channels/instagram/connect')
            ->assertOk()
            ->assertJsonPath('data.platform', 'instagram')
            ->assertJsonPath('data.method', 'meta')
            ->assertJsonPath('data.cta', 'Connect via Meta')
            ->assertJsonPath('data.steps', ['Log in to Facebook', 'Choose your Instagram account', 'Review the permissions and save'])
            ->assertJsonPath('data.options.0', ['title' => 'Connect with Instagram login', 'note' => 'For accounts without a Facebook Page.', 'url' => null])
            ->assertJsonPath('data.ineligible.title', 'This is a personal account.')
            ->assertJsonPath('data.state', 'default')
            ->assertJsonPath('data.channel', null)
            ->assertJsonPath('data.access_request', null);

        $this->getJson('/api/v1/onboarding/channels/tiktok/connect')->assertJsonPath('data.method', 'tiktok')->assertJsonPath('data.cta', 'Continue with TikTok');
    });

    it('answers in Arabic with ?lang=ar', function () {
        $this->getJson('/api/v1/onboarding/channels/whatsapp/connect?lang=ar')
            ->assertOk()
            ->assertJsonPath('data.name', 'WhatsApp')
            ->assertJsonPath('data.steps.0', 'سجّل الدخول إلى Facebook')
            ->assertJsonPath('data.options.2.title', 'دليل حل المشكلات');
    });

    it('opens in reconnect when the owner’s channel dropped, and connected when it is active', function () {
        $channel = Channel::factory()->for($this->user)->create(['platform' => 'instagram', 'status' => 'expired']);

        $this->getJson('/api/v1/onboarding/channels/instagram/connect')
            ->assertJsonPath('data.state', 'reconnect')
            ->assertJsonPath('data.channel.id', $channel->id);

        $channel->update(['status' => 'active']);
        $this->getJson('/api/v1/onboarding/channels/instagram/connect')->assertJsonPath('data.state', 'connected');
    });

    it('opens in requested while a Request access is pending', function () {
        $request = ChannelAccessRequest::factory()->for($this->user)->create(['channel_id' => Channel::factory()->create(['platform' => 'instagram'])->id]);

        $this->getJson('/api/v1/onboarding/channels/instagram/connect')
            ->assertJsonPath('data.state', 'requested')
            ->assertJsonPath('data.access_request.id', $request->id);
        $this->getJson('/api/v1/onboarding/channels/messenger/connect')->assertJsonPath('data.state', 'default');
    });

    it('is 404 for an unknown or hidden channel, and needs a token', function () {
        ChannelConnectScreen::where('platform', 'tiktok')->update(['active' => false]);

        $this->getJson('/api/v1/onboarding/channels/tiktok/connect')->assertNotFound();
        $this->getJson('/api/v1/onboarding/channels/myspace/connect')->assertNotFound();

        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer nope')->getJson('/api/v1/onboarding/channels/instagram/connect')->assertUnauthorized();
    });
});

describe('POST /api/v1/channels/instagram connect rules', function () {
    it('refuses an account another workspace connected, with a token to request access', function () {
        $taken = Channel::factory()->create(['platform' => 'instagram', 'account_id' => '17840001', 'username' => 'nourboutique']);
        fakeInstagramSignIn();

        $response = $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => instagramState($this->user)])
            ->assertStatus(409)
            ->assertJsonPath('code', 'account_taken')
            ->assertJsonPath('account.handle', 'nourboutique');

        expect(Channel::count())->toBe(1)
            ->and(ChannelAccessRequest::channelFromToken($response->json('access_request_token'), $this->user)?->is($taken))->toBeTrue();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'subscribed_apps'));
    });

    it('restores a dropped channel on reconnect, so its automations run again', function () {
        $channel = Channel::factory()->for($this->user)->create(['platform' => 'instagram', 'account_id' => '17840001', 'status' => 'expired', 'access_token' => 'old']);
        Automation::factory()->create(['user_id' => $this->user->id, 'channel_id' => $channel->id]);
        fakeInstagramSignIn();

        $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => instagramState($this->user)])
            ->assertCreated()
            ->assertJsonPath('data.id', $channel->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('meta.reconnected', true);

        expect($channel->fresh())->status->toBe('active')->access_token->toBe('IGAA-new')
            ->and(Channel::count())->toBe(1);
    });

    it('says a new connection is not a reconnect', function () {
        fakeInstagramSignIn();

        $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => instagramState($this->user)])
            ->assertCreated()
            ->assertJsonPath('meta.reconnected', false);
    });

    it('reports access_denied when the owner cancelled or refused in Instagram', function () {
        $this->postJson('/api/v1/channels/instagram?lang=ar', ['error' => 'access_denied', 'error_reason' => 'user_denied', 'state' => instagramState($this->user)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'access_denied')
            ->assertJsonPath('message', 'لم يُربط Instagram. أغلقت النافذة أو لم تسمح بالوصول.');

        Http::assertNothingSent();
    });

    it('reports account_ineligible for a personal account', function () {
        fakeInstagramSignIn(['account_type' => 'PERSONAL', 'username' => 'nour.personal']);

        $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => instagramState($this->user)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'account_ineligible')
            ->assertJsonPath('account', ['platform' => 'instagram', 'handle' => 'nour.personal', 'type' => 'PERSONAL']);

        expect(Channel::count())->toBe(0);
    });

    it('still needs a code when Instagram sent no error', function () {
        $this->postJson('/api/v1/channels/instagram', ['state' => instagramState($this->user)])->assertJsonValidationErrors(['code']);
    });
});

describe('Request access', function () {
    beforeEach(function () {
        $this->channel = Channel::factory()->create(['platform' => 'instagram', 'username' => 'nourboutique']);
        $this->token = ChannelAccessRequest::tokenFor($this->user, $this->channel);
    });

    it('records one pending request per account, and lists it', function () {
        $this->postJson('/api/v1/channels/access-requests', ['token' => $this->token])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'instagram')
            ->assertJsonPath('data.account.handle', 'nourboutique')
            ->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/v1/channels/access-requests', ['token' => $this->token])->assertOk();

        expect(ChannelAccessRequest::where('user_id', $this->user->id)->where('channel_id', $this->channel->id)->count())->toBe(1);

        $this->getJson('/api/v1/channels/access-requests')->assertOk()->assertJsonCount(1, 'data');
    });

    it('refuses a token that is missing, forged, expired or another owner’s', function () {
        $this->postJson('/api/v1/channels/access-requests', [])->assertJsonValidationErrors(['token']);
        $this->postJson('/api/v1/channels/access-requests', ['token' => 'forged'])->assertJsonValidationErrors(['token']);
        $this->postJson('/api/v1/channels/access-requests', ['token' => ChannelAccessRequest::tokenFor(User::factory()->create(), $this->channel)])
            ->assertJsonValidationErrors(['token']);

        $this->travel(61)->minutes();
        $this->postJson('/api/v1/channels/access-requests?lang=ar', ['token' => $this->token])
            ->assertJsonValidationErrors(['token' => 'انتهت صلاحية هذا الطلب. اربط الحساب مرة أخرى لتطلب الوصول.']);

        expect(ChannelAccessRequest::count())->toBe(0);
    });

    it('shows an owner only their own requests', function () {
        ChannelAccessRequest::factory()->create();

        $this->getJson('/api/v1/channels/access-requests')->assertOk()->assertJsonCount(0, 'data');
    });
});
