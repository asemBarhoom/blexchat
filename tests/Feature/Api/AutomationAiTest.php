<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->create(['slug' => Plan::FREE_SLUG, 'has_ai' => false]);
    $this->owner = User::factory()->create();
    $this->automation = Automation::factory()->for($this->owner)->for(Channel::factory()->for($this->owner))->create();
});

it('turns Blexchat AI on and off on a plan that includes it', function () {
    Subscription::factory()->for($this->owner)->for(Plan::factory()->create(['slug' => 'pro', 'has_ai' => true]))->create();

    $this->actingAs($this->owner)->patchJson("/api/v1/automations/{$this->automation->id}/ai", ['enabled' => true])
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $this->automation->id, 'ai_enabled' => true]]);
    $this->getJson("/api/v1/automations/{$this->automation->id}")->assertJsonPath('data.ai_enabled', true);

    $this->patchJson("/api/v1/automations/{$this->automation->id}/ai", ['enabled' => false])->assertJsonPath('data.ai_enabled', false);
});

it('asks for an upgrade to turn it on without AI in the plan, but always lets it be turned off', function () {
    $this->actingAs($this->owner)->patchJson("/api/v1/automations/{$this->automation->id}/ai", ['enabled' => true])
        ->assertForbidden()
        ->assertJsonPath('code', 'plan_required');
    expect($this->automation->refresh()->ai_enabled)->toBeFalse();

    $this->automation->update(['ai_enabled' => true]);
    $this->patchJson("/api/v1/automations/{$this->automation->id}/ai", ['enabled' => false])->assertOk();
    expect($this->automation->refresh()->ai_enabled)->toBeFalse();
});

it('keeps the switch to the owner and to a yes or no', function () {
    $this->actingAs(User::factory()->create())->patchJson("/api/v1/automations/{$this->automation->id}/ai", ['enabled' => false])->assertForbidden();
    $this->actingAs($this->owner)->patchJson("/api/v1/automations/{$this->automation->id}/ai", ['enabled' => 'maybe'])->assertJsonValidationErrors('enabled');
});
