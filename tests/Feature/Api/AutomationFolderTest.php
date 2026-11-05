<?php

use App\Models\Automation;
use App\Models\AutomationFolder;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create();
    $this->automation = Automation::factory()->for($this->owner)->for($this->channel)->create();
});

it('creates a folder, files an automation in it and lists the folder', function () {
    $folder = $this->actingAs($this->owner)->postJson('/api/v1/automation-folders', ['name' => '  Summer sale '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Summer sale')
        ->assertJsonPath('data.automations_count', 0)
        ->json('data.id');

    $this->patchJson("/api/v1/automations/{$this->automation->id}", ['automation_folder_id' => $folder])
        ->assertOk()
        ->assertJsonPath('data.automation_folder_id', $folder);
    Automation::factory()->for($this->owner)->for($this->channel)->create();

    $this->getJson("/api/v1/automations?folder_id={$folder}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->automation->id);
    $this->getJson('/api/v1/automation-folders')->assertExactJson(['data' => [['id' => $folder, 'name' => 'Summer sale', 'automations_count' => 1]]]);

    $this->patchJson("/api/v1/automations/{$this->automation->id}", ['automation_folder_id' => null])->assertJsonPath('data.automation_folder_id', null);
});

it('renames a folder and keeps names unique per owner', function () {
    $ramadan = AutomationFolder::factory()->for($this->owner)->create(['name' => 'Ramadan']);
    $eid = AutomationFolder::factory()->for($this->owner)->create(['name' => 'Eid']);
    AutomationFolder::factory()->create(['name' => 'Summer']);

    $this->actingAs($this->owner)->postJson('/api/v1/automation-folders', ['name' => 'Ramadan'])->assertJsonValidationErrors('name');
    $this->patchJson("/api/v1/automation-folders/{$eid->id}", ['name' => 'Ramadan'])->assertJsonValidationErrors('name');
    $this->patchJson("/api/v1/automation-folders/{$ramadan->id}", ['name' => 'Ramadan'])->assertOk();
    $this->patchJson("/api/v1/automation-folders/{$eid->id}", ['name' => 'Summer'])->assertOk()->assertJsonPath('data.name', 'Summer');
});

it('deletes a folder and leaves its automations running, outside any folder', function () {
    $folder = AutomationFolder::factory()->for($this->owner)->create();
    $this->automation->update(['automation_folder_id' => $folder->id, 'active' => true]);

    $this->actingAs($this->owner)->deleteJson("/api/v1/automation-folders/{$folder->id}")->assertNoContent();

    expect($this->automation->refresh())->automation_folder_id->toBeNull()->active->toBeTrue()->trashed()->toBeFalse();
});

it('keeps folders to their owner', function () {
    $theirs = AutomationFolder::factory()->create();

    $this->actingAs($this->owner)->patchJson("/api/v1/automation-folders/{$theirs->id}", ['name' => 'Mine'])->assertForbidden();
    $this->deleteJson("/api/v1/automation-folders/{$theirs->id}")->assertForbidden();
    $this->patchJson("/api/v1/automations/{$this->automation->id}", ['automation_folder_id' => $theirs->id])->assertJsonValidationErrors('automation_folder_id');
    $this->getJson('/api/v1/automation-folders')->assertJsonCount(0, 'data');
});

it('stops at 50 folders', function () {
    AutomationFolder::factory()->count(AutomationFolder::MAX_PER_USER)->for($this->owner)->create();

    $this->actingAs($this->owner)->postJson('/api/v1/automation-folders', ['name' => 'One more'])->assertJsonValidationErrors('name');
});
