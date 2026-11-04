<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\Instagram\InstagramEventHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.instagram.graph_version' => 'v21.0']);

    $this->channel = Channel::factory()->create(['platform' => 'instagram', 'account_id' => '17840001', 'access_token' => 'IGAA-long-lived', 'status' => 'active']);
    $this->handler = app(InstagramEventHandler::class);

    Http::fake([
        'graph.instagram.com/v21.0/media-1*' => Http::response(['caption' => 'عباية الشتاء', 'media_type' => 'IMAGE']),
        'graph.instagram.com/v21.0/17840001/messages' => Http::response(['recipient_id' => '9001', 'message_id' => 'mid-out-1']),
        'graph.instagram.com/v21.0/*/replies' => Http::response(['id' => 'reply-1']),
        'graph.instagram.com/v21.0/9001*' => Http::response(['username' => 'reem.k']),
        'graph.instagram.com/v21.0/5005*' => Http::response(['name' => 'Sara M.', 'username' => 'sara.m', 'profile_pic' => 'https://cdn.example.com/sara.jpg']),
    ]);
});

/**
 * @return array<string, mixed>
 */
function contactComment(string $id, string $text): array
{
    return ['id' => $id, 'text' => $text, 'from' => ['id' => '9001', 'username' => 'reem.k'], 'media' => ['id' => 'media-1', 'media_product_type' => 'FEED']];
}

it('saves a commenter as a contact, credited to the automation that answered them', function () {
    $automation = Automation::factory()->for($this->channel->user)->for($this->channel)->create([
        'trigger_type' => 'comment_keyword', 'keywords' => ['السعر'],
        'actions' => [['type' => 'send_dm', 'message' => 'السعر ٢٤٩ ريال']],
    ]);

    $this->handler->comment($this->channel, contactComment('c1', 'السعر؟'));
    $this->handler->comment($this->channel, contactComment('c2', 'شكراً'));

    $contact = Contact::sole();
    expect($contact->user_id)->toBe($this->channel->user_id)
        ->and($contact->channel_id)->toBe($this->channel->id)
        ->and($contact->platform_id)->toBe('9001')
        ->and($contact->name)->toBe('reem.k')
        ->and($contact->handle)->toBe('reem.k')
        ->and($contact->source_automation_id)->toBe($automation->id)
        ->and(Conversation::sole()->contact_id)->toBe($contact->id);
});

it('saves someone who messages directly, with the name Instagram shares, and no automation', function () {
    $this->handler->message($this->channel, ['sender' => ['id' => '5005'], 'timestamp' => now()->getTimestampMs(), 'message' => ['mid' => 'm1', 'text' => 'السلام عليكم']]);
    $this->handler->message($this->channel, ['sender' => ['id' => '5005'], 'timestamp' => now()->getTimestampMs(), 'message' => ['mid' => 'm2', 'text' => 'عندكم مقاس ٥٨؟']]);

    $contact = Contact::sole();
    expect($contact->name)->toBe('Sara M.')
        ->and($contact->handle)->toBe('sara.m')
        ->and($contact->avatar)->toBe('https://cdn.example.com/sara.jpg')
        ->and($contact->source_automation_id)->toBeNull()
        ->and($contact->last_active_at)->not->toBeNull()
        ->and(Conversation::sole()->contact_id)->toBe($contact->id);
});

it('does not credit a later automation to someone already saved', function () {
    $this->handler->message($this->channel, ['sender' => ['id' => '9001'], 'message' => ['mid' => 'm1', 'text' => 'hello']]);
    Automation::factory()->for($this->channel->user)->for($this->channel)->create([
        'trigger_type' => 'comment_keyword', 'keywords' => ['السعر'],
        'actions' => [['type' => 'reply_comment', 'message' => 'بالخاص 💌']],
    ]);

    $this->handler->comment($this->channel, contactComment('c1', 'السعر؟'));

    expect(Contact::sole()->source_automation_id)->toBeNull();
});
