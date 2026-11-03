<?php

use App\Models\Channel;
use App\Models\ChannelOption;
use App\Models\User;
use Database\Seeders\ChannelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChannelOptionSeeder::class);
    $this->user = User::factory()->create();
});

it('needs a signed-in owner', function () {
    $this->getJson('/api/v1/onboarding/channels')->assertUnauthorized();
});

it('lists the four channels the frontend shows, in order', function () {
    $this->actingAs($this->user)->getJson('/api/v1/onboarding/channels')
        ->assertOk()
        ->assertJsonPath('data.*.id', ['instagram', 'whatsapp', 'tiktok', 'messenger'])
        ->assertJsonPath('data.0', [
            'id' => 'instagram',
            'name' => 'Instagram',
            'line' => 'Comments, DMs and story replies',
            'connected' => false,
        ]);
});

it('follows the admin order', function () {
    ChannelOption::where('platform', 'messenger')->update(['sort' => -1]);

    $this->actingAs($this->user)->getJson('/api/v1/onboarding/channels')
        ->assertJsonPath('data.*.id', ['messenger', 'instagram', 'whatsapp', 'tiktok']);
});

it('marks only the platforms this owner has connected', function () {
    Channel::factory()->for($this->user)->create(['platform' => 'tiktok']);
    Channel::factory()->for(User::factory())->create(['platform' => 'instagram']);

    $this->actingAs($this->user)->getJson('/api/v1/onboarding/channels')
        ->assertJsonPath('data.*.connected', [false, false, true, false]);
});

it('answers in Arabic with ?lang=ar', function () {
    $this->actingAs($this->user)->getJson('/api/v1/onboarding/channels?lang=ar')
        ->assertJsonPath('data.1.name', 'واتساب')
        ->assertJsonPath('data.1.line', 'محادثاتك مع عملائك');
});

it('hides channels switched off in the panel', function () {
    ChannelOption::where('platform', 'whatsapp')->update(['active' => false]);

    $this->actingAs($this->user)->getJson('/api/v1/onboarding/channels')
        ->assertJsonPath('data.*.id', ['instagram', 'tiktok', 'messenger']);
});

it('seeds again without duplicating channels', function () {
    $this->seed(ChannelOptionSeeder::class);

    expect(ChannelOption::count())->toBe(4);
});
