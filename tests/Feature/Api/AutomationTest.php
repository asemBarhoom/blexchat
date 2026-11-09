<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/v1/automations', function () {
    it('returns paginated automations for the authenticated user', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        Automation::factory()->count(5)->create(['user_id' => $user->id, 'channel_id' => $channel->id]);
        Automation::factory()->create(); // another user

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/automations')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 5);
    });

    it('filters by channel_id', function () {
        $user = User::factory()->create();
        $channel1 = Channel::factory()->create(['user_id' => $user->id]);
        $channel2 = Channel::factory()->create(['user_id' => $user->id]);
        Automation::factory()->count(2)->create(['user_id' => $user->id, 'channel_id' => $channel1->id]);
        Automation::factory()->count(3)->create(['user_id' => $user->id, 'channel_id' => $channel2->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/automations?channel_id={$channel1->id}")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    });

    it('searches names ignoring case, and counts the live ones whatever the status filter', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $make = fn (string $name, bool $active) => Automation::factory()->create(['user_id' => $user->id, 'channel_id' => $channel->id, 'name' => $name, 'active' => $active]);
        $make('Comment PRICE', true);
        $make('Price list DM', false);
        $make('Story reply', true);
        Automation::factory()->create(['name' => 'Price elsewhere', 'active' => true]); // another user

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/automations?search=price')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.live', 1)
            ->assertJsonCount(2, 'data');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/automations?status=inactive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.live', 2);
    });
});

describe('POST /api/v1/automations', function () {
    it('creates an automation', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/automations', [
                'name' => 'Delivery inquiry',
                'channel_id' => $channel->id,
                'trigger_type' => 'comment_keyword',
                'keywords' => ['ship', 'deliver'],
                'actions' => [
                    ['type' => 'reply_comment', 'message' => 'We ship nationwide!'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Delivery inquiry')
            ->assertJsonPath('data.active', true);
    });

    it('returns 403 when channel does not belong to user', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(); // not the user's

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/automations', [
                'name' => 'Test',
                'channel_id' => $channel->id,
                'trigger_type' => 'comment_keyword',
                'actions' => [['type' => 'reply_comment', 'message' => 'Hi']],
            ])
            ->assertStatus(403);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/automations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'channel_id', 'trigger_type', 'actions']);
    });
});

describe('PATCH /api/v1/automations/{id}', function () {
    it('returns the Blexchat AI extras it saved', function () {
        $user = User::factory()->create();
        $automation = Automation::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/automations/{$automation->id}", ['extras' => [
                'ai_goal' => 'Answer sizing questions and link the catalogue.',
                'ai_comments' => true,
                'review_before_send' => false,
            ]])
            ->assertOk()
            ->assertJsonPath('data.extras.ai_goal', 'Answer sizing questions and link the catalogue.')
            ->assertJsonPath('data.extras.ai_comments', true)
            ->assertJsonPath('data.extras.review_before_send', false);

        $this->getJson("/api/v1/automations/{$automation->id}")
            ->assertJsonPath('data.extras.ai_goal', 'Answer sizing questions and link the catalogue.');
    });
});

describe('PATCH /api/v1/automations/{id}/toggle', function () {
    it('toggles an automation off', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $automation = Automation::factory()->create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/automations/{$automation->id}/toggle", ['active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('automations', ['id' => $automation->id, 'active' => false]);
    });
});

describe('DELETE /api/v1/automations/{id}', function () {
    it('moves own automation to the Trash, stopped, for 30 days', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $automation = Automation::factory()->create(['user_id' => $user->id, 'channel_id' => $channel->id, 'active' => true]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/automations/{$automation->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.purges_at', now()->addDays(30)->toIso8601String());

        $this->assertSoftDeleted('automations', ['id' => $automation->id, 'active' => false]);
        $this->getJson('/api/v1/automations')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/automations?trashed=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $automation->id);
        $this->getJson("/api/v1/automations/{$automation->id}")->assertNotFound();
    })->freezeTime();

    it('does not run an automation that is in the Trash', function () {
        $automation = Automation::factory()->create(['active' => true]);

        $automation->moveToTrash();

        expect(Automation::where('channel_id', $automation->channel_id)->where('active', true)->exists())->toBeFalse();
    });
});

describe('POST /api/v1/automations/{id}/restore', function () {
    it('brings an automation back from the Trash, still stopped', function () {
        $user = User::factory()->create();
        $automation = Automation::factory()->for($user)->create(['active' => true]);
        $automation->moveToTrash();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/automations/{$automation->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.trashed_at', null);

        $this->assertNotSoftDeleted('automations', ['id' => $automation->id]);
    });

    it('restores only the owner’s automations, and only from the Trash', function () {
        $user = User::factory()->create();
        $automation = Automation::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/automations/{$automation->id}/restore")->assertNotFound();

        $automation->moveToTrash();
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/v1/automations/{$automation->id}/restore")->assertForbidden();
    });
});

it('deletes automations for good once they have been in the Trash for 30 days', function () {
    $old = Automation::factory()->create();
    $recent = Automation::factory()->create();
    $old->moveToTrash();
    $recent->moveToTrash();
    $old->forceFill(['deleted_at' => now()->subDays(30)])->save();

    $this->artisan('model:prune', ['--model' => [Automation::class]])->assertSuccessful();

    $this->assertDatabaseMissing('automations', ['id' => $old->id]);
    $this->assertSoftDeleted('automations', ['id' => $recent->id]);
});
