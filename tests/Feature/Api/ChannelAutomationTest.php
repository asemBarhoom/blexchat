<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs keyword automations for WhatsApp, Messenger and TikTok fake messages', function (string $platform) {
    config()->set('services.channels.driver', 'fake');

    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => $platform]);
    $automation = Automation::factory()->for($owner)->for($channel)->create([
        'trigger_type' => 'dm_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'send_dm', 'message' => 'Here is the price.']],
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/channels/{$channel->id}/test-events", [
            'type' => 'message',
            'from' => 'customer',
            'text' => 'What is the price?',
        ])
        ->assertCreated();

    $conversationId = $response->json('data.conversation_id');
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversationId,
        'automation_id' => $automation->id,
        'automated' => true,
        'content' => 'Here is the price.',
    ]);
})->with(['whatsapp', 'messenger', 'tiktok']);
