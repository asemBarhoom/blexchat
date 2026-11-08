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

it('restores a deleted automation from the trash', function () {
    $automation = Automation::factory()->create();
    $automation->delete();

    Livewire::test(ListAutomations::class)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$automation])
        ->callAction(TestAction::make('restore')->table($automation));

    $this->assertDatabaseHas('automations', [
        'id' => $automation->id,
        'deleted_at' => null,
    ]);
});

it('renders the automation list and trashed detail page', function () {
    $automation = Automation::factory()->create();
    $automation->delete();

    $this->get('/admin/automations')->assertOk()->assertSee('المجلد');
    $this->get('/admin/automations/'.$automation->id)->assertOk();
});
