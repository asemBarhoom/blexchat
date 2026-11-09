<?php

use App\Jobs\ProcessMetaChannelWebhook;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('deduplicates identical signed Meta webhook deliveries', function () {
    Bus::fake();
    config(['services.meta.app_secret' => 'secret']);
    $body = ['object' => 'page', 'entry' => []];
    $raw = json_encode($body);
    $signature = 'sha256='.hash_hmac('sha256', $raw, 'secret');

    $this->postJson('/api/v1/webhooks/messenger', $body, ['X-Hub-Signature-256' => $signature])->assertOk();
    $this->postJson('/api/v1/webhooks/messenger', $body, ['X-Hub-Signature-256' => $signature])->assertOk();

    Bus::assertDispatchedTimes(ProcessMetaChannelWebhook::class, 1);
});

it('reports channel readiness and unsupported TikTok sends', function () {
    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => 'tiktok', 'status' => 'active', 'access_token' => 'token']);

    $this->actingAs($owner)->getJson("/api/v1/channels/{$channel->id}/health")
        ->assertOk()
        ->assertJsonPath('data.ready', true)
        ->assertJsonPath('data.send_supported', false);
});

it('serves the public help centre', function () {
    $this->getJson('/api/v1/help')->assertOk()->assertJsonStructure(['data' => ['title', 'articles', 'support'], 'meta' => ['locale']]);
});
