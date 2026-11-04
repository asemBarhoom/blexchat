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
