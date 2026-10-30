<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/v1/channels/connect', function () {
    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/v1/channels/connect')->assertStatus(401);
    });

    it('connects a new channel', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/channels/connect', [
                'platform' => 'instagram',
                'access_token' => 'EAATjTKF4xxx',
                'account_id' => '17841400000',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.platform', 'instagram');
    });

    it('returns 409 when channel already connected', function () {
        $user = User::factory()->create();
        Channel::factory()->create([
            'user_id' => $user->id,
            'platform' => 'instagram',
            'account_id' => '17841400000',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/channels/connect', [
                'platform' => 'instagram',
                'access_token' => 'token',
                'account_id' => '17841400000',
            ])
            ->assertStatus(409);
    });

    it('rejects invalid platform', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/channels/connect', [
                'platform' => 'twitter',
                'access_token' => 'token',
                'account_id' => '123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    });
});

describe('DELETE /api/v1/channels/{channel}', function () {
    it('disconnects own channel', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/channels/{$channel->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    });

    it('returns 403 when deleting another user channel', function () {
        $user = User::factory()->create();
        $other = Channel::factory()->create(); // different user

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/channels/{$other->id}")
            ->assertStatus(403);
    });
});
