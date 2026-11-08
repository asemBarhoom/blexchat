<?php

use App\Mail\TeamInvitationMail;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->plan = Plan::factory()->free()->create(['teammates_limit' => 3, 'has_assignment' => true]);
    $this->owner = User::factory()->create(['name' => 'Reem', 'business_name' => 'Reem Abayas']);
    $this->channel = Channel::factory()->for($this->owner)->create();
    $this->join = function (User $teammate): User {
        $teammate->forceFill(['team_owner_id' => $this->owner->id])->save();

        return $teammate;
    };
});

it('invites a teammate by email with a link to the join page', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/team/invitations', ['email' => 'Sara@Example.com'])
        ->assertCreated()
        ->assertJsonPath('data.invitations.0.email', 'sara@example.com')
        ->assertJsonPath('data.seats', ['used' => 2, 'limit' => 3])
        ->assertJsonPath('data.members.0.role', 'owner');

    Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail): bool {
        $mail->assertSeeInHtml('Reem invited you to answer messages in the Reem Abayas inbox on Blexchat.');
        $mail->assertSeeInHtml('/team/join?token='.$mail->token);

        return $mail->hasTo('sara@example.com') && TeamInvitation::findByToken($mail->token) !== null;
    });
});

it('refuses an invite past the plan seats, but resends an open one', function () {
    TeamInvitation::factory()->for($this->owner, 'owner')->create(['email' => 'one@example.com']);
    ($this->join)(User::factory()->create());

    $this->actingAs($this->owner)
        ->postJson('/api/v1/team/invitations', ['email' => 'two@example.com'])
        ->assertForbidden()
        ->assertJsonPath('code', 'seat_limit');

    $this->postJson('/api/v1/team/invitations', ['email' => 'one@example.com'])->assertCreated();
    Mail::assertSent(TeamInvitationMail::class, 1);
});

it('refuses inviting yourself or a teammate', function () {
    $teammate = ($this->join)(User::factory()->create());

    $this->actingAs($this->owner)->postJson('/api/v1/team/invitations', ['email' => $this->owner->email])->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/team/invitations', ['email' => $teammate->email])->assertJsonValidationErrors('email');
});

it('lets the invited person join with the same email, once', function () {
    [, $token] = TeamInvitation::issue($this->owner, 'sara@example.com');
    $sara = User::factory()->create(['email' => 'sara@example.com']);

    $this->actingAs($sara)
        ->postJson('/api/v1/team/invitations/accept', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.role', 'agent')
        ->assertJsonPath('data.members.1.email', 'sara@example.com')
        ->assertJsonPath('data.invitations', []);

    expect($sara->refresh()->team_owner_id)->toBe($this->owner->id);
    $this->postJson('/api/v1/team/invitations/accept', ['token' => $token])->assertNotFound()->assertJsonPath('code', 'invitation_invalid');
});

it('refuses a join from another email, an account with its own channels, or an expired invitation', function () {
    [, $token] = TeamInvitation::issue($this->owner, 'sara@example.com');

    $this->actingAs(User::factory()->create(['email' => 'other@example.com']))
        ->postJson('/api/v1/team/invitations/accept', ['token' => $token])
        ->assertForbidden()
        ->assertJsonPath('code', 'other_email');

    $sara = User::factory()->create(['email' => 'sara@example.com']);
    Channel::factory()->for($sara)->create();
    $this->actingAs($sara)->postJson('/api/v1/team/invitations/accept', ['token' => $token])->assertForbidden()->assertJsonPath('code', 'has_own_account');

    TeamInvitation::query()->update(['expires_at' => now()->subMinute()]);
    $this->postJson('/api/v1/team/invitations/accept', ['token' => $token])->assertNotFound();
});

it('gives a teammate the owner inbox and contacts, and nothing else', function () {
    $teammate = ($this->join)(User::factory()->create());
    $conversation = Conversation::factory()->for($this->channel)->create();
    Contact::factory()->forUser($this->owner)->create(['name' => 'Huda']);

    $this->actingAs($teammate)->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.id', $conversation->id);
    $this->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertOk();
    $this->getJson('/api/v1/contacts')->assertOk()->assertJsonFragment(['name' => 'Huda']);
    $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.role', 'agent');

    foreach (['/api/v1/automations', '/api/v1/subscription', '/api/v1/dashboard', '/api/v1/ai/settings'] as $ownerPage) {
        $this->getJson($ownerPage)->assertForbidden()->assertJsonPath('code', 'owner_only');
    }

    $this->postJson('/api/v1/team/invitations', ['email' => 'x@example.com'])->assertForbidden();
});

it('assigns a conversation to a teammate on plans with assignment', function () {
    $teammate = ($this->join)(User::factory()->create(['name' => 'Sara']));
    $conversation = Conversation::factory()->for($this->channel)->create();
    Conversation::factory()->for($this->channel)->create();

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/conversations/{$conversation->id}", ['assigned_to' => $teammate->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', ['id' => $teammate->id, 'name' => 'Sara']);

    $this->actingAs($teammate)->getJson('/api/v1/conversations?assigned=me')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $conversation->id);
    $this->patchJson("/api/v1/conversations/{$conversation->id}", ['assigned_to' => User::factory()->create()->id])->assertJsonValidationErrors('assigned_to');

    $this->plan->update(['has_assignment' => false]);
    $this->patchJson("/api/v1/conversations/{$conversation->id}", ['assigned_to' => $this->owner->id])->assertForbidden()->assertJsonPath('code', 'plan_required');
});

it('unassigns a removed teammate conversations', function () {
    $teammate = ($this->join)(User::factory()->create());
    $conversation = Conversation::factory()->for($this->channel)->create(['assigned_to' => $teammate->id]);

    $this->actingAs($this->owner)
        ->deleteJson("/api/v1/team/members/{$teammate->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.members');

    expect($teammate->refresh()->team_owner_id)->toBeNull()
        ->and($conversation->refresh()->assigned_to)->toBeNull();
    $this->deleteJson('/api/v1/team/members/'.User::factory()->create()->id)->assertNotFound();
});
