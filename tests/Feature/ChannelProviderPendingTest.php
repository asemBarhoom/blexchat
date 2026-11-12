<?php

use App\Models\User;
use Database\Seeders\ChannelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ChannelOptionSeeder::class);
});

it('returns a translated provider pending response for unconfigured oauth platforms', function (string $platform, string $locale, string $provider, string $channel) {
    config(['services.channels.oauth_driver' => 'provider', "services.{$platform}.client_id" => null, 'services.channels.oauth_redirect_url' => null]);
    $owner = User::factory()->create();
    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/channels/oauth/{$platform}/start?lang={$locale}");

    $response->assertStatus(503)->assertJson(['code' => 'provider_pending', 'platform' => $platform]);
    expect($response->json('message'))->toContain($channel)->toContain($provider);
})->with([
    'messenger en' => ['messenger', 'en', 'Meta', 'Messenger'],
    'whatsapp ar' => ['whatsapp', 'ar', 'Meta', 'WhatsApp'],
    'tiktok en' => ['tiktok', 'en', 'TikTok', 'TikTok'],
]);

it('returns availability and pending reasons in the channel picker', function () {
    config(['services.channels.oauth_driver' => 'provider', 'services.messenger.client_id' => null, 'services.whatsapp.client_id' => 'configured', 'services.tiktok.client_id' => null, 'services.channels.oauth_redirect_url' => 'https://api.test/callback']);
    $owner = User::factory()->create();
    $data = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/onboarding/channels?lang=en')->assertOk()->json('data');
    $options = collect($data)->keyBy('id');

    expect($options['instagram'])->toMatchArray(['available' => true, 'pending_reason' => null])
        ->and($options['messenger']['available'])->toBeFalse()
        ->and($options['messenger']['pending_reason'])->toContain('Meta')
        ->and($options['whatsapp'])->toMatchArray(['available' => true, 'pending_reason' => null])
        ->and($options['tiktok']['available'])->toBeFalse();
});
