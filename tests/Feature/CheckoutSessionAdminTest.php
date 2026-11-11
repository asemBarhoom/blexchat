<?php

use App\Filament\Resources\CheckoutSessionResource\Pages\ListCheckoutSessions;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\CheckoutSessionsRelationManager;
use App\Models\CheckoutSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
    app()->setLocale('ar');
});

it('lists checkout sessions and opens one', function () {
    $open = CheckoutSession::factory()->create();
    $complete = CheckoutSession::factory()->complete()->create();

    Livewire::test(ListCheckoutSessions::class)
        ->assertCanSeeTableRecords([$open, $complete]);

    $this->get('/admin/checkout-sessions')
        ->assertOk()
        ->assertSee('جلسات الدفع');

    $this->get('/admin/checkout-sessions/'.$open->id)
        ->assertOk()
        ->assertSee($open->stripe_session_id);
});

it('filters checkout sessions by status', function () {
    $open = CheckoutSession::factory()->create();
    $complete = CheckoutSession::factory()->complete()->create();

    Livewire::test(ListCheckoutSessions::class)
        ->filterTable('status', 'complete')
        ->assertCanSeeTableRecords([$complete])
        ->assertCanNotSeeTableRecords([$open]);
});

it('shows one owner’s checkout sessions on their page', function () {
    $owner = User::factory()->create();
    $session = CheckoutSession::factory()->for($owner)->create();

    $this->get('/admin/users/'.$owner->id)->assertOk()->assertSee('جلسات الدفع');

    Livewire::test(CheckoutSessionsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
        ->assertCanSeeTableRecords([$session]);
});

it('keeps non-admins out', function () {
    $this->actingAs(User::where('email', 'nour@nourboutique.com')->firstOrFail())
        ->get('/admin/checkout-sessions')
        ->assertForbidden();
});
