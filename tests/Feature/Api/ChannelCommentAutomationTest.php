<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs public comment automations for Facebook Messenger and TikTok fake comments', function (string $platform) {
    config()->set('services.channels.driver', 'fake');
    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => $platform]);
    $automation = Automation::factory()->for($owner)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'reply_comment', 'message' => 'The price is on our page.']],
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'comment', 'from' => 'customer', 'text' => 'What is the price?'])
        ->assertCreated();

    expect(Comment::where('channel_id', $channel->id)->firstOrFail())
        ->automation_id->toBe($automation->id)
        ->automated_reply->toBeTrue();
})->with(['messenger', 'tiktok']);
