<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/v1/automations', function () {
    it('returns paginated automations for the authenticated user', function () {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        Automation::factory()->count(5)->create(['user_id' => $user->id, 'channel_id' => $channel->id]);
        Automation::factory()->create(); // another user

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/automations')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 5);
    });

    it('filters by channel_id', function () {
        $user     = User::factory()->create();
        $channel1 = Channel::factory()->create(['user_id' => $user->id]);
        $channel2 = Channel::factory()->create(['user_id' => $user->id]);
        Automation::factory()->count(2)->create(['user_id' => $user->id, 'channel_id' => $channel1->id]);
        Automation::factory()->count(3)->create(['user_id' => $user->id, 'channel_id' => $channel2->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/automations?channel_id={$channel1->id}")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    });
});

describe('POST /api/v1/automations', function () {
    it('creates an automation', function () {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/automations', [
                'name'         => 'Delivery inquiry',
                'channel_id'   => $channel->id,
                'trigger_type' => 'comment_keyword',
                'keywords'     => ['ship', 'deliver'],
                'actions'      => [
                    ['type' => 'reply_comment', 'message' => 'We ship nationwide!'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Delivery inquiry')
            ->assertJsonPath('data.active', true);
    });

    it('returns 403 when channel does not belong to user', function () {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create(); // not the user's

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/automations', [
                'name'         => 'Test',
                'channel_id'   => $channel->id,
                'trigger_type' => 'comment_keyword',
                'actions'      => [['type' => 'reply_comment', 'message' => 'Hi']],
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

describe('PATCH /api/v1/automations/{id}/toggle', function () {
    it('toggles an automation off', function () {
        $user       = User::factory()->create();
        $channel    = Channel::factory()->create(['user_id' => $user->id]);
        $automation = Automation::factory()->create([
            'user_id'    => $user->id,
            'channel_id' => $channel->id,
            'active'     => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/automations/{$automation->id}/toggle", ['active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('automations', ['id' => $automation->id, 'active' => false]);
    });
});

describe('DELETE /api/v1/automations/{id}', function () {
    it('deletes own automation', function () {
        $user       = User::factory()->create();
        $channel    = Channel::factory()->create(['user_id' => $user->id]);
        $automation = Automation::factory()->create(['user_id' => $user->id, 'channel_id' => $channel->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/automations/{$automation->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('automations', ['id' => $automation->id]);
    });
});
