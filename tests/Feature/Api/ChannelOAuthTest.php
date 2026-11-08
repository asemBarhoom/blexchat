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
