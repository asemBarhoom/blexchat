<?php

use App\Filament\Resources\AutomationResource\Pages\ListAutomations;
use App\Models\Automation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('restores a deleted automation', function () {
    $automation = Automation::factory()->create();
    $automation->delete();

    Livewire::test(ListAutomations::class)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$automation])
        ->callAction(TestAction::make('restore')->table($automation));

    expect($automation->refresh()->deleted_at)->toBeNull();
});

it('renders the automation list with the folder column', function () {
    $this->get('/admin/automations')
        ->assertOk()
        ->assertSee('المجلد');
});
