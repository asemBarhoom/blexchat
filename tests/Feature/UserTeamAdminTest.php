<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@blexchat.test')->firstOrFail();
    $this->actingAs($this->admin);

    $this->owner = User::factory()->create(['email' => 'reem@abayas.test', 'business_info' => 'Abayas, delivery in Riyadh in 2 days.']);
    $this->teammate = User::factory()->create(['email' => 'sara@abayas.test']);
    $this->teammate->forceFill(['team_owner_id' => $this->owner->id])->save();
});

it('shows who is an owner and who is a teammate, and filters by role', function () {
    Livewire::test(ListUsers::class)
        ->filterTable('role', 'agent')
        ->assertCanSeeTableRecords([$this->teammate])
        ->assertCanNotSeeTableRecords([$this->owner]);
});

it('shows the team, the open invitations and the business info on the user page', function () {
    TeamInvitation::factory()->for($this->owner, 'owner')->create(['email' => 'lina@abayas.test']);

    $this->get('/admin/users/'.$this->owner->id)
        ->assertOk()
        ->assertSee('sara@abayas.test')
        ->assertSee('lina@abayas.test')
        ->assertSee('Abayas, delivery in Riyadh in 2 days.');
});

it('refuses to delete an owner who still has teammates', function () {
    Livewire::test(ListUsers::class)
        ->assertActionDisabled(TestAction::make('delete')->table($this->owner))
        ->assertActionEnabled(TestAction::make('delete')->table($this->teammate));

    $this->teammate->delete();

    Livewire::test(ListUsers::class)->callAction(TestAction::make('delete')->table($this->owner));

    $this->assertModelMissing($this->owner);
});

it('skips the signed-in admin, other admins and owners with a team when deleting in bulk', function () {
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $loner = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$this->admin, $otherAdmin, $this->owner, $loner])
        ->callAction(TestAction::make('delete')->table()->bulk());

    $this->assertModelMissing($loner);
    $this->assertModelExists($this->admin);
    $this->assertModelExists($otherAdmin);
    $this->assertModelExists($this->owner);
    $this->assertModelExists($this->teammate);
});

it('withdraws an invitation from the user page', function () {
    $invitation = TeamInvitation::factory()->for($this->owner, 'owner')->create(['email' => 'lina@abayas.test']);

    Livewire::test(ViewUser::class, ['record' => $this->owner->getRouteKey()])
        ->callAction('withdrawInvitation', ['invitation_id' => $invitation->id]);

    $this->assertModelMissing($invitation);
});
