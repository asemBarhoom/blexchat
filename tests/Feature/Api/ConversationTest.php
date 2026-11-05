<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/v1/conversations', function () {
    it('returns conversations for authenticated user channels', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        Conversation::factory()->count(3)->create(['channel_id' => $channel->id]);
        Conversation::factory()->create(); // different channel

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/conversations')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });
});

describe('GET /api/v1/conversations/{id}/messages', function () {
    it('returns messages for a conversation', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);
        Message::factory()->count(5)->create(['conversation_id' => $conversation->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
    });

    it('puts the later of two messages sent in the same second first', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create();
        $sentAt = now()->startOfSecond();
        $question = Message::factory()->for($conversation)->create(['sent_at' => $sentAt]);
        $autoReply = Message::factory()->for($conversation)->create(['sent_at' => $sentAt]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertJsonPath('data.0.id', $autoReply->id)
            ->assertJsonPath('data.1.id', $question->id);
    });

    it('returns 403 for conversation from another user channel', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(); // different channel

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(403);
    });
});

describe('POST /api/v1/conversations/{id}/messages', function () {
    it('sends a text message', function () {
        $user = User::factory()->create();
        // Instagram sends through its API; that path is covered in InstagramIntegrationTest.
        $channel = Channel::factory()->create(['user_id' => $user->id, 'platform' => 'whatsapp']);
        $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'type' => 'text',
                'content' => 'Hello, your order is on the way!',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'sent_at']]);
    });

    it('validates button fields for text_with_button type', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'type' => 'text_with_button',
                'content' => 'Track here:',
                // missing button_label and button_url
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['button_label', 'button_url']);
    });
});

describe('PATCH /api/v1/conversations/{id}', function () {
    it('marks a conversation done, reopens it, and marks it read', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create(['status' => 'open', 'unread_count' => 3]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.unread', 3);

        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['read' => true])
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.unread', 0);

        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'open'])->assertJsonPath('data.status', 'open');
        $this->getJson('/api/v1/conversations?status=resolved')->assertJsonCount(0, 'data');
    });

    it('lists only unread conversations when asked', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->for($user)->create();
        $unread = Conversation::factory()->for($channel)->create(['unread_count' => 2]);
        Conversation::factory()->for($channel)->create(['unread_count' => 0]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/conversations?unread=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);
    });

    it('keeps changes to the owner and to known states', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'resolved'])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'snoozed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson("/api/v1/conversations/{$conversation->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'read']);
    });
});

describe('/api/v1/inbox/settings', function () {
    it('accepts new conversations until the owner switches it off', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/inbox/settings')->assertOk()->assertExactJson(['data' => ['accepting_new' => true]]);

        $this->patchJson('/api/v1/inbox/settings', ['accepting_new' => false])->assertOk()->assertJsonPath('data.accepting_new', false);
        $this->getJson('/api/v1/inbox/settings')->assertJsonPath('data.accepting_new', false);

        $this->patchJson('/api/v1/inbox/settings', [])->assertUnprocessable()->assertJsonValidationErrors('accepting_new');
    });
});
