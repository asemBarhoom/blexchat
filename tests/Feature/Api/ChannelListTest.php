<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('GET /api/v1/channels', function () {
    it('returns 401 when unauthenticated', function () {
        $this->getJson('/api/v1/channels')->assertUnauthorized();
    });

    it('lists the owner’s channels, oldest connection first, with the fields of one channel', function () {
        $tiktok = Channel::factory()->for($this->user)->create(['platform' => 'tiktok', 'connected_at' => now()->subDay(), 'status' => 'expired']);
        $instagram = Channel::factory()->for($this->user)->create([
            'platform' => 'instagram',
            'username' => '@nourboutique',
            'profile_picture' => 'avatars/sara.webp',
            'followers' => 12400,
            'connected_at' => now()->subWeek(),
        ]);
        Channel::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/channels')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$instagram->id, $tiktok->id])
            ->assertJsonPath('data.0', [
                'id' => $instagram->id,
                'platform' => 'instagram',
                'account_id' => $instagram->account_id,
                'username' => '@nourboutique',
                'profile_picture' => url('/storage/avatars/sara.webp'),
                'followers' => 12400,
                'status' => 'active',
                'connected_at' => $instagram->connected_at->toIso8601String(),
                'token_expires_at' => null,
            ])
            ->assertJsonPath('data.1.status', 'expired');
    });

    it('returns an empty list before the first channel connects', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/channels')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    });
});
