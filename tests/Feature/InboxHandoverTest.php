<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.instagram.driver' => 'fake']);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    Automation::factory()->for($this->owner)->for($this->channel)->create(['trigger_type' => 'dm_keyword', 'keywords' => ['deliver'], 'actions' => [['type' => 'send_dm', 'message' => 'Yes, in 2–4 days']]]);
    $this->dm = fn (string $text): int => $this->actingAs($this->owner)
        ->postJson("/api/v1/channels/{$this->channel->id}/test-events", ['type' => 'message', 'text' => $text, 'from' => 'reem.k'])
        ->assertCreated()
        ->json('data.conversation_id');
});

it('hands a conversation over when nothing answers what an automation was handling', function () {
    $conversation = ($this->dm)('do you deliver?');
    $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.state', 'automation');

    ($this->dm)('and do you have it in size 40?');
    ($this->dm)('hello?');

    $this->getJson('/api/v1/conversations?state=human')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $conversation);
    $handovers = collect($this->getJson("/api/v1/conversations/{$conversation}/messages?lang=ar")->json('data'))->where('type', 'handover');

    expect($handovers)->toHaveCount(1)
        ->and($handovers->first())->toMatchArray(['direction' => 'system', 'reason' => 'سأل عن شيء لا تجيب عنه الأتمتة.', 'automated' => false]);
});

it('leaves a conversation no automation ever answered as open', function () {
    ($this->dm)('hi there');

    $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.state', 'open');
    expect(Conversation::first()->messages()->where('type', 'handover')->exists())->toBeFalse();
});

it('takes the conversation out of Needs you when the owner replies or marks it done', function (string $how) {
    $conversation = ($this->dm)('do you deliver?');
    ($this->dm)('size 40?');

    match ($how) {
        'reply' => $this->postJson("/api/v1/conversations/{$conversation}/messages", ['type' => 'text', 'content' => 'Yes, we have 40'])->assertCreated(),
        'done' => $this->patchJson("/api/v1/conversations/{$conversation}", ['status' => 'resolved'])->assertOk(),
    };

    expect(Conversation::find($conversation)->state)->toBe('open');
    $this->getJson('/api/v1/conversations?state=human')->assertJsonCount(0, 'data');
})->with(['reply', 'done']);

it('keeps the handover line out of the contact’s message count and the automation’s sends', function () {
    $conversation = ($this->dm)('do you deliver?');
    ($this->dm)('size 40?');
    $contact = Conversation::find($conversation)->contact_id;

    $this->getJson("/api/v1/contacts/{$contact}")->assertJsonPath('data.conversation.messages_count', 3);
    $this->getJson('/api/v1/automations/'.Automation::first()->id.'/stats')->assertJsonPath('data.sends', 1);
});
