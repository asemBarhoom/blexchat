<?php

use App\Jobs\SendAutomationFollowUp;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Instagram\AutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.instagram.driver' => 'fake']);
    Http::preventStrayRequests();
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram', 'account_id' => 'fake-'.$this->owner->id, 'status' => 'active']);
    $this->automation = fn (string $trigger, array $extras): Automation => Automation::factory()->for($this->owner)->for($this->channel)->create([
        'trigger_type' => $trigger,
        'keywords' => ['price'],
        'actions' => [['type' => 'send_dm_with_button', 'message' => 'The jacket is 249 SAR', 'button_label' => 'Shop', 'button_url' => 'https://shop.example.com']],
        'extras' => $extras,
    ]);
    $this->event = fn (string $type, string $text): ?int => $this->actingAs($this->owner)
        ->postJson("/api/v1/channels/{$this->channel->id}/test-events", ['type' => $type, 'text' => $text, 'from' => 'reem.k'])
        ->assertCreated()
        ->json('data.conversation_id');
    $this->sent = fn (int $conversation): array => Message::where('conversation_id', $conversation)->where('direction', 'outbound')->orderBy('id')->pluck('content')->all();
});

it('asks a commenter to follow first and sends the DM once they reply', function () {
    ($this->automation)('comment_keyword', ['ask_follow' => true, 'follow_text' => 'Follow us to get the price', 'follow_button' => 'I followed']);

    $conversation = ($this->event)('comment', 'price?');

    expect(($this->sent)($conversation))->toBe(['Follow us to get the price'])
        ->and(Conversation::find($conversation)->awaiting)->toBe('follow');

    ($this->event)('message', 'I followed');

    expect(($this->sent)($conversation))->toBe(['Follow us to get the price', 'The jacket is 249 SAR'])
        ->and(Conversation::find($conversation)->awaiting)->toBeNull();
});

it('asks for an email after the DM and saves the address they reply with', function () {
    ($this->automation)('dm_keyword', ['ask_email' => true, 'email_ask' => 'What is your email?']);

    $conversation = ($this->event)('message', 'price please');

    expect(($this->sent)($conversation))->toBe(['The jacket is 249 SAR', 'What is your email?']);

    ($this->event)('message', 'sure, it is Reem.K@Example.com.');

    expect(Contact::sole()->email)->toBe('reem.k@example.com')
        ->and(Conversation::find($conversation))->awaiting->toBeNull()->state->toBe('automation');
});

it('waits for a commenter to write back before asking for their email', function () {
    ($this->automation)('comment_keyword', ['ask_email' => true, 'email_ask' => 'What is your email?']);

    $conversation = ($this->event)('comment', 'price?');

    expect(($this->sent)($conversation))->toBe(['The jacket is 249 SAR'])
        ->and(Conversation::find($conversation)->awaiting)->toBe('email_question');

    ($this->event)('message', 'thanks!');
    ($this->event)('message', 'reem@example.com');

    expect(($this->sent)($conversation))->toBe(['The jacket is 249 SAR', 'What is your email?'])
        ->and(Contact::sole()->email)->toBe('reem@example.com');
});

it('lets a reply without an email go to the usual matching', function () {
    ($this->automation)('dm_keyword', ['ask_email' => true, 'email_ask' => 'What is your email?']);
    $conversation = ($this->event)('message', 'price please');

    ($this->event)('message', 'no thanks');

    expect(Contact::sole()->email)->toBeNull()
        ->and(Conversation::find($conversation))->awaiting->toBeNull()->state->toBe('human');
});

it('stops waiting once the owner answers', function () {
    ($this->automation)('dm_keyword', ['ask_email' => true, 'email_ask' => 'What is your email?']);
    $conversation = ($this->event)('message', 'price please');

    $this->postJson("/api/v1/conversations/{$conversation}/messages", ['type' => 'text', 'content' => 'Hi Reem, I can help.'])->assertCreated();
    ($this->event)('message', 'reem@example.com');

    expect(Contact::sole()->email)->toBeNull();
});

it('times the follow-up from the DM', function () {
    ($this->automation)('dm_keyword', ['follow_up' => true, 'follow_up_after' => '6h', 'follow_up_text' => 'Still thinking about it?']);

    ($this->event)('message', 'price please');

    Queue::assertPushed(SendAutomationFollowUp::class, fn (SendAutomationFollowUp $job): bool => $job->dm->content === 'The jacket is 249 SAR'
        && $job->delay->between(now()->addMinutes(359), now()->addMinutes(361)));
});

it('sends the follow-up once, only to someone who did not act on the DM', function (Closure $meanwhile, bool $sends) {
    $automation = ($this->automation)('dm_keyword', ['follow_up' => true, 'follow_up_after' => '1h', 'follow_up_text' => 'Still thinking about it?']);
    $conversation = ($this->event)('message', 'price please');
    $dm = Message::where('conversation_id', $conversation)->where('direction', 'outbound')->sole();

    $meanwhile($dm, $automation, $this);
    (new SendAutomationFollowUp($dm->refresh()))->handle(app(AutomationRunner::class));
    (new SendAutomationFollowUp($dm))->handle(app(AutomationRunner::class));

    expect(Message::where('conversation_id', $conversation)->where('content', 'Still thinking about it?')->count())->toBe($sends ? 1 : 0);
})->with([
    'nothing happened' => [fn () => null, true],
    'they tapped the button' => [fn (Message $dm) => $dm->recordClick(), false],
    'they wrote back' => [fn (Message $dm, Automation $automation, $test) => ($test->event)('message', 'hmm'), false],
    'the automation was stopped' => [fn (Message $dm, Automation $automation) => $automation->update(['active' => false]), false],
]);
