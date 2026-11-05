<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.instagram.driver' => 'fake']);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    Automation::factory()->for($this->owner)->for($this->channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'reply_comment', 'message' => 'Check your DMs'], ['type' => 'send_dm', 'message' => 'The jacket is 249 SAR']],
    ]);
    $this->comment = fn (string $text): ?int => $this->actingAs($this->owner)
        ->postJson("/api/v1/channels/{$this->channel->id}/test-events", ['type' => 'comment', 'text' => $text, 'from' => 'reem.k'])
        ->assertCreated()
        ->json('data.conversation_id');
});

it('opens a comment-to-DM thread with the comment and the post it was on', function () {
    $conversation = ($this->comment)('price please?');

    $thread = $this->getJson("/api/v1/conversations/{$conversation}/messages")->assertOk()->json('data');

    expect($thread)->toHaveCount(2)
        ->and($thread[1])->toMatchArray(['direction' => 'inbound', 'type' => 'comment', 'via' => 'comment', 'content' => 'price please?'])
        ->and($thread[1]['post'])->toHaveKeys(['id', 'caption', 'thumbnail_url'])
        ->and($thread[0])->toMatchArray(['direction' => 'outbound', 'content' => 'The jacket is 249 SAR'])->not->toHaveKey('via');
});

it('adds each later comment once, without counting it as unread', function () {
    $conversation = ($this->comment)('price?');
    ($this->comment)('price for size 40?');

    expect(Message::where('conversation_id', $conversation)->where('type', 'comment')->count())->toBe(2);
    $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread', 0);
});

it('leaves comments that opened no conversation out of the inbox', function () {
    expect(($this->comment)('love this'))->toBeNull()
        ->and(Message::where('type', 'comment')->exists())->toBeFalse();
});
