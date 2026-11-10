<?php

use App\Filament\Resources\ConversationResource\Pages\ListConversations;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('shows a user workspaces and saved locale in the admin page', function () {
    $user = User::factory()->create(['locale' => 'ar']);
    $workspace = $user->workspaces()->create(['name' => 'Bakery']);
    Channel::factory()->for($user)->for($workspace)->create();

    $this->get('/admin/users/'.$user->id)->assertOk()->assertSee('مساحات العمل')->assertSee('Bakery')->assertSee('العربية');
});

it('filters conversations by workspace', function () {
    $owner = User::factory()->create();
    $first = $owner->workspaces()->create(['name' => 'First']);
    $second = $owner->workspaces()->create(['name' => 'Second']);
    $one = Conversation::factory()->for(Channel::factory()->for($owner)->for($first))->create();
    $two = Conversation::factory()->for(Channel::factory()->for($owner)->for($second))->create();

    Livewire::test(ListConversations::class)->filterTable('workspace_id', $first->id)
        ->assertCanSeeTableRecords([$one])->assertCanNotSeeTableRecords([$two]);
});

it('removes teammates when deleting a workspace and keeps the last one', function () {
    $owner = User::factory()->create();
    $first = $owner->workspaces()->create(['name' => 'First']);
    $second = $owner->workspaces()->create(['name' => 'Second']);
    $teammate = User::factory()->create(['team_owner_id' => $owner->id, 'workspace_id' => $second->id]);

    $second->deleteWithTeam();
    expect($teammate->fresh()->workspace_id)->toBeNull()->and(Workspace::find($second->id))->toBeNull();
    expect($owner->workspaces()->count())->toBe(1);
    $this->get('/admin/users/'.$owner->id)->assertOk();
});
