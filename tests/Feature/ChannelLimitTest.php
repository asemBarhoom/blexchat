<?php

use App\Models\Channel;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->owner = User::factory()->create();
    Channel::factory()->for($this->owner)->create(['platform' => 'whatsapp', 'account_id' => 'wa-1']);
});

it('refuses a second channel on the free plan, counting every workspace', function () {
    $other = Workspace::factory()->for($this->owner, 'owner')->create();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/channels/connect', ['platform' => 'tiktok', 'access_token' => 'tok', 'account_id' => 'tt-1'], ['X-Workspace' => $other->id, 'Accept-Language' => 'ar'])
        ->assertForbidden()
        ->assertJsonPath('code', 'channel_limit')
        ->assertJsonPath('limit', 1)
        ->assertJsonPath('message', 'باقتك تسمح بقناة واحدة. رقِّ باقتك لربط المزيد.');

    expect(Channel::count())->toBe(1);
});

it('connects more channels on a plan with no channel limit', function () {
    $pro = Plan::factory()->create(['slug' => 'pro', 'channels_limit' => null]);
    Subscription::factory()->for($this->owner)->create(['plan_id' => $pro->id]);

    $this->actingAs($this->owner->refresh())
        ->postJson('/api/v1/channels/connect', ['platform' => 'tiktok', 'access_token' => 'tok', 'account_id' => 'tt-1'])
        ->assertCreated();

    expect(Channel::count())->toBe(2);
});

it('never counts a reconnect of an account already connected', function () {
    expect($this->owner->channelLimitRefusal('whatsapp', 'wa-1'))->toBeNull()
        ->and($this->owner->channelLimitRefusal('whatsapp', 'wa-2'))->toMatchArray(['code' => 'channel_limit', 'limit' => 1]);
});
