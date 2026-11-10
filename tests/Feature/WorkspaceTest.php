<?php

use App\Mail\TeamInvitationMail;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->free()->create(['teammates_limit' => 5]);
    $this->owner = User::factory()->create(['business_name' => 'Agency']);
    $this->first = Workspace::defaultFor($this->owner);
    $this->second = Workspace::factory()->for($this->owner, 'owner')->create(['name' => 'Bakery']);
    $this->firstChannel = Channel::factory()->for($this->owner)->create();
    $this->secondChannel = Channel::factory()->for($this->owner)->create(['workspace_id' => $this->second->id]);
});

it('works in the workspace the X-Workspace header names, else the first', function () {
    $this->actingAs($this->owner)->getJson('/api/v1/channels')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->firstChannel->id);

    $this->getJson('/api/v1/channels', ['X-Workspace' => $this->second->id])
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->secondChannel->id);

    $this->deleteJson("/api/v1/channels/{$this->secondChannel->id}")->assertForbidden();
});

it('names the current workspace and the saved language on the user', function () {
    $this->actingAs($this->owner)->getJson('/api/v1/auth/me', ['X-Workspace' => $this->second->id, 'Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.workspace', ['id' => $this->second->id, 'name' => 'Bakery'])
        ->assertJsonPath('data.locale', 'ar');
});

it('refuses a workspace that is not the owner’s', function () {
    $someoneElses = Workspace::factory()->create();

    $this->actingAs($this->owner)->getJson('/api/v1/channels', ['X-Workspace' => $someoneElses->id])
        ->assertNotFound()->assertJsonPath('code', 'workspace_not_found');
});

it('keeps contacts apart per workspace, the plan’s usage counting them all', function () {
    Contact::factory()->for($this->owner)->create(['workspace_id' => $this->second->id]);
    Contact::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->getJson('/api/v1/contacts', ['X-Workspace' => $this->second->id])
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.usage.used', 2);
});

it('adds workspaces up to the plan’s limit, and never deletes the last', function () {
    $this->actingAs($this->owner)->postJson('/api/v1/workspaces', ['name' => 'Florist'])
        ->assertForbidden()->assertJsonPath('code', 'workspace_limit');

    $advanced = Plan::factory()->create(['slug' => 'advanced', 'workspaces_limit' => 10]);
    Subscription::factory()->for($this->owner)->create(['plan_id' => $advanced->id]);
    $this->actingAs($this->owner->refresh());

    $this->postJson('/api/v1/workspaces', ['name' => 'Florist'])->assertCreated()->assertJsonPath('data.name', 'Florist');
    $this->getJson('/api/v1/workspaces', ['X-Workspace' => $this->second->id])
        ->assertOk()->assertJsonPath('meta', ['used' => 3, 'limit' => 10])->assertJsonPath('data.1.current', true);

    $this->deleteJson("/api/v1/workspaces/{$this->second->id}")->assertOk();
    expect(Channel::find($this->secondChannel->id))->toBeNull();

    Workspace::where('owner_id', $this->owner->id)->where('name', 'Florist')->delete();
    $this->deleteJson("/api/v1/workspaces/{$this->first->id}")->assertStatus(409)->assertJsonPath('code', 'last_workspace');
});

it('invites a teammate into one workspace, where they see only that workspace', function () {
    Mail::fake();

    $this->actingAs($this->owner)->postJson('/api/v1/team/invitations', ['email' => 'sara@example.com'], ['X-Workspace' => $this->second->id])->assertCreated();
    expect(TeamInvitation::sole()->workspace_id)->toBe($this->second->id);

    $token = null;
    Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    $sara = User::factory()->create(['email' => 'sara@example.com']);
    $this->actingAs($sara)->postJson('/api/v1/team/invitations/accept', ['token' => $token])->assertOk();

    $this->getJson('/api/v1/channels')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->secondChannel->id);
    $this->getJson('/api/v1/channels', ['X-Workspace' => $this->first->id])->assertNotFound();

    $this->actingAs($this->owner)->getJson('/api/v1/team')->assertOk()->assertJsonCount(1, 'data.members');
});
