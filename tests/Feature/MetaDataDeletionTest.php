<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.meta.app_secret' => 'meta-secret', 'services.instagram.app_secret' => 'instagram-secret', 'app.frontend_url' => 'https://site.test']);
});

function metaSignedRequest(string $userId, string $secret): string
{
    $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $payload = $encode(json_encode(['algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => $userId]));

    return $encode(hash_hmac('sha256', $payload, $secret, true)).'.'.$payload;
}

it('disconnects the person\'s channels when they remove the app in Meta', function () {
    $channel = Channel::factory()->for(User::factory())->create(['platform' => 'instagram', 'account_id' => '1784', 'status' => 'active']);
    $other = Channel::factory()->for(User::factory())->create(['platform' => 'instagram', 'account_id' => '9999', 'status' => 'active']);

    $this->postJson('/api/v1/webhooks/meta/deauthorize', ['signed_request' => metaSignedRequest('1784', 'instagram-secret')])->assertOk();

    expect($channel->fresh()->status)->toBe('inactive')
        ->and($channel->fresh()->access_token)->toBe('')
        ->and($other->fresh()->status)->toBe('active');
});

it('deletes the person\'s channels and returns a status url and confirmation code', function () {
    $channel = Channel::factory()->for(User::factory())->create(['platform' => 'messenger', 'account_id' => '555']);

    $response = $this->postJson('/api/v1/webhooks/meta/data-deletion', ['signed_request' => metaSignedRequest('555', 'meta-secret')])
        ->assertOk()
        ->assertJsonStructure(['url', 'confirmation_code']);

    expect(Channel::find($channel->id))->toBeNull()
        ->and($response->json('url'))->toBe('https://site.test/data-deletion?code='.$response->json('confirmation_code'));
});

it('refuses a signed request that was not signed with our app secret', function (string $route) {
    $channel = Channel::factory()->for(User::factory())->create(['platform' => 'instagram', 'account_id' => '1784']);

    $this->postJson($route, ['signed_request' => metaSignedRequest('1784', 'wrong-secret')])->assertStatus(400);
    $this->postJson($route, ['signed_request' => 'not-a-signed-request'])->assertStatus(400);

    expect($channel->fresh())->not->toBeNull()->and($channel->fresh()->status)->toBe('active');
})->with(['/api/v1/webhooks/meta/deauthorize', '/api/v1/webhooks/meta/data-deletion']);

it('finds channels connected through Facebook Login by the person’s Facebook id', function () {
    $channel = Channel::factory()->for(User::factory())->create(['platform' => 'messenger', 'account_id' => 'page-1', 'meta_user_id' => 'fb-person-1']);

    $this->postJson('/api/v1/webhooks/meta/data-deletion', ['signed_request' => metaSignedRequest('fb-person-1', 'meta-secret')])->assertOk();

    expect(Channel::find($channel->id))->toBeNull();
});
