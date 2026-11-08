<?php

use App\Filament\Resources\PlanResource\Pages\ManagePlans;
use App\Models\Plan;
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

it('shows team plan fields in the plans list', function () {
    $this->get('/admin/plans')
        ->assertOk()
        ->assertSee('المقاعد');
});

it('allows an admin to save team seats and conversation assignment', function () {
    $pro = Plan::where('slug', 'pro')->firstOrFail();

    Livewire::test(ManagePlans::class)
        ->callAction(TestAction::make('edit')->table($pro), [
            'teammates_limit' => 5,
            'has_assignment' => true,
        ])
        ->assertHasNoFormErrors();

    expect($pro->fresh())
        ->teammates_limit->toBe(5)
        ->has_assignment->toBeTrue();
});
