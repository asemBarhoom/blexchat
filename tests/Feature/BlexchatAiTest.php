<?php

use App\Models\AiUsage;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.instagram.driver' => 'fake', 'services.anthropic.driver' => 'claude', 'services.anthropic.key' => 'test-key']);
    Http::preventStrayRequests();
    $this->plan = Plan::factory()->free()->create(['has_ai' => true, 'ai_replies_limit' => 250]);
    $this->owner = User::factory()->create(['business_name' => 'Reem Abayas', 'business_info' => 'Delivery in Riyadh takes 2–4 days.']);
    $this->channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    $this->automation = Automation::factory()->for($this->owner)->for($this->channel)->create(['trigger_type' => 'dm_keyword', 'keywords' => ['abaya'], 'ai_enabled' => true, 'actions' => [['type' => 'send_dm', 'message' => 'Our abayas are here']]]);
    $this->dm = fn (string $text): int => $this->actingAs($this->owner)
        ->postJson("/api/v1/channels/{$this->channel->id}/test-events", ['type' => 'message', 'text' => $text, 'from' => 'reem.k'])
        ->assertCreated()
        ->json('data.conversation_id');
    $this->claudeSays = fn (array $answer) => Http::fake(['api.anthropic.com/*' => Http::response([
        'model' => 'claude-test',
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => json_encode($answer)]],
    ])]);
});

it('answers what no keyword matches from the business info', function () {
    config(['services.anthropic.input_cost_per_million' => 3, 'services.anthropic.output_cost_per_million' => 15]);
    ($this->claudeSays)(['handover' => false, 'reply' => 'Yes! Riyadh delivery takes 2–4 days.', 'reason' => '']);

    $conversation = ($this->dm)('abaya please');
    ($this->dm)('how long is delivery to Riyadh?');

    expect(Conversation::find($conversation)->state)->toBe('automation');
    $messages = collect($this->getJson("/api/v1/conversations/{$conversation}/messages")->json('data'));
    expect($messages->firstWhere('by_ai', true))->toMatchArray(['direction' => 'outbound', 'content' => "Yes! Riyadh delivery takes 2–4 days.\n\n⚡ Sent with Blexchat", 'by_ai' => true, 'automation_id' => $this->automation->id])
        ->and($messages->where('type', 'handover'))->toBeEmpty();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->header('x-api-key') === ['test-key']
        && str_contains($request['system'], 'Delivery in Riyadh takes 2–4 days.')
        && str_contains($request['messages'][0]['content'], "Customer: abaya please\nBusiness: Our abayas are here\n\n⚡ Sent with Blexchat\nCustomer: how long is delivery to Riyadh?")
        && $request['output_config']['format']['type'] === 'json_schema');

    expect(AiUsage::sole()->only(['status', 'model', 'input_tokens', 'output_tokens', 'estimated_cost_micros']))->toMatchArray([
        'status' => AiUsage::STATUS_COMPLETED,
        'model' => 'claude-test',
        'input_tokens' => 100,
        'output_tokens' => 20,
        'estimated_cost_micros' => 600,
    ]);
});

it('hands over without calling Claude after the monthly reply limit', function () {
    $this->plan->update(['ai_replies_limit' => 1]);
    AiUsage::factory()->for($this->owner)->create(['status' => AiUsage::STATUS_COMPLETED]);

    $conversation = ($this->dm)('abaya please');
    ($this->dm)('how long is delivery?');

    expect(Conversation::find($conversation)->state)->toBe('human')
        ->and(Conversation::find($conversation)->messages()->where('type', 'handover')->value('content'))->toBe('ai_limit');
    Http::assertNothingSent();
});

it('does not count last month usage against this month', function () {
    $this->plan->update(['ai_replies_limit' => 1]);
    AiUsage::factory()->for($this->owner)->create([
        'status' => AiUsage::STATUS_COMPLETED,
        'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
    ]);
    ($this->claudeSays)(['handover' => false, 'reply' => 'Two days.', 'reason' => '']);

    ($this->dm)('abaya please');
    ($this->dm)('how long is delivery?');

    Http::assertSentCount(1);
    expect($this->owner->aiUsage())->toMatchArray(['used' => 1, 'limit' => 1, 'remaining' => 0]);
});

it('hands over with its one-line reason when the business info does not answer', function () {
    ($this->claudeSays)(['handover' => true, 'reply' => '', 'reason' => 'Asked about size 40 and a delivery date.']);

    $conversation = ($this->dm)('abaya please');
    ($this->dm)('do you have size 40 by Friday?');

    expect(Conversation::find($conversation)->state)->toBe('human');
    $handover = collect($this->getJson("/api/v1/conversations/{$conversation}/messages")->json('data'))->firstWhere('type', 'handover');
    expect($handover)->toMatchArray(['content' => 'ai_unsure', 'reason' => 'Asked about size 40 and a delivery date.']);
});

it('hands over as before when the AI cannot run', function (string $case) {
    match ($case) {
        'switch off' => $this->automation->update(['ai_enabled' => false]),
        'plan without AI' => $this->plan->update(['has_ai' => false]),
        'no business info' => $this->owner->update(['business_info' => null]),
        'no key' => config(['services.anthropic.key' => null]),
    };

    $conversation = ($this->dm)('abaya please');
    ($this->dm)('how long is delivery?');

    expect(Conversation::find($conversation)->messages()->where('type', 'handover')->value('content'))->toBe('unmatched');
    Http::assertNothingSent();
})->with(['switch off', 'plan without AI', 'no business info', 'no key']);

it('hands over as before when Claude fails or refuses', function (array $response, int $status) {
    Http::fake(['api.anthropic.com/*' => Http::response($response, $status)]);

    $conversation = ($this->dm)('abaya please');
    ($this->dm)('how long is delivery?');

    expect(Conversation::find($conversation)->messages()->where('type', 'handover')->value('content'))->toBe($status === 200 ? 'ai_unsure' : 'unmatched');

    if ($status !== 200) {
        expect(AiUsage::sole()->status)->toBe(AiUsage::STATUS_FAILED);
    }
})->with([
    'server error' => [['type' => 'error'], 529],
    'refusal' => [['stop_reason' => 'refusal', 'content' => []], 200],
]);

it('lets the owner write the business info Blexchat AI answers from', function () {
    $this->actingAs($this->owner)
        ->patchJson('/api/v1/ai/settings', ['business_info' => '  Open 10–10, Sat–Thu.  '])
        ->assertOk()
        ->assertJsonPath('data.business_info', 'Open 10–10, Sat–Thu.')
        ->assertJsonPath('data.included', true)
        ->assertJsonPath('data.live', true)
        ->assertJsonPath('data.usage.used', 0)
        ->assertJsonPath('data.usage.limit', 250)
        ->assertJsonPath('data.usage.remaining', 250);

    $this->patchJson('/api/v1/ai/settings', ['business_info' => str_repeat('a', 5001)])->assertJsonValidationErrors('business_info');
    $this->getJson('/api/v1/ai/settings')->assertJsonPath('data.business_info', 'Open 10–10, Sat–Thu.');
});
