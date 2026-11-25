<?php

use App\Jobs\ProcessMetaChannelWebhook;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Channels\ChannelEventHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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

it('answers a Facebook Page comment from the webhook, publicly and privately through the Graph API', function () {
    config()->set('services.channels.driver', 'provider');
    Http::fake([
        '*/c-1/comments' => Http::response(['id' => 'reply-1']),
        '*/me/messages' => Http::response(['message_id' => 'mid-1']),
    ]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => 'messenger', 'account_id' => 'page-1', 'status' => 'active']);
    Automation::factory()->for($owner)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [
            ['type' => 'reply_comment', 'message' => 'Check your inbox.'],
            ['type' => 'send_dm', 'message' => 'Here is the price list.'],
        ],
    ]);
    $change = fn (string $commentId, string $from, string $verb = 'add'): array => ['field' => 'feed', 'value' => [
        'item' => 'comment', 'verb' => $verb, 'comment_id' => $commentId, 'post_id' => 'page-1_post-1',
        'from' => ['id' => $from, 'name' => 'Sara'], 'message' => 'What is the price?',
    ]];

    (new ProcessMetaChannelWebhook('messenger', [['id' => 'page-1', 'changes' => [
        $change('c-1', 'user-1'),
        $change('c-2', 'page-1'),
        $change('c-3', 'user-2', 'edited'),
    ]]]))->handle(app(ChannelEventHandler::class));

    expect(Comment::pluck('platform_comment_id')->all())->toBe(['c-1'])
        ->and(Comment::first()->automated_reply)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'c-1/comments') && $request['message'] === 'Check your inbox.');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'me/messages') && $request['recipient'] === ['comment_id' => 'c-1']);
});

it('sends a Facebook commenter the private reply with a real button to its tracked link', function () {
    config()->set('services.channels.driver', 'provider');
    Http::fake(['*/me/messages' => Http::response(['message_id' => 'mid-1'])]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => 'messenger', 'account_id' => 'page-1', 'status' => 'active']);
    Automation::factory()->for($owner)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'send_dm_with_button', 'message' => 'Here is the link you asked for:', 'button_label' => 'Open the link', 'button_url' => 'https://shop.test']],
    ]);

    (new ProcessMetaChannelWebhook('messenger', [['id' => 'page-1', 'changes' => [['field' => 'feed', 'value' => [
        'item' => 'comment', 'verb' => 'add', 'comment_id' => 'c-1', 'post_id' => 'page-1_post-1',
        'from' => ['id' => 'user-1', 'name' => 'Sara'], 'message' => 'What is the price?',
    ]]]]]))->handle(app(ChannelEventHandler::class));

    Http::assertSent(function (Request $request): bool {
        $payload = $request['message']['attachment']['payload'] ?? [];

        return $request['recipient'] === ['comment_id' => 'c-1']
            && ($payload['template_type'] ?? null) === 'button'
            && $payload['text'] === 'Here is the link you asked for:'
            && $payload['buttons'][0]['title'] === 'Open the link'
            && str_contains($payload['buttons'][0]['url'], '/l/');
    });
});

it('names a Facebook commenter in the inbox, also when they wrote before without a name', function () {
    config()->set('services.channels.driver', 'provider');
    Http::fake(['*/me/messages' => Http::response(['message_id' => 'mid-1'])]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => 'messenger', 'account_id' => 'page-1', 'status' => 'active']);
    Automation::factory()->for($owner)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'send_dm', 'message' => 'Here is the price list.']],
    ]);
    $earlier = Conversation::factory()->for($channel)->create(['platform_conversation_id' => 'user-1', 'contact_platform_id' => 'user-1', 'contact_name' => null]);
    $this->travel(2)->hours();

    (new ProcessMetaChannelWebhook('messenger', [['id' => 'page-1', 'changes' => [['field' => 'feed', 'value' => [
        'item' => 'comment', 'verb' => 'add', 'comment_id' => 'c-1', 'post_id' => 'page-1_post-1',
        'from' => ['id' => 'user-1', 'name' => 'Sara'], 'message' => 'What is the price?',
    ]]]]]))->handle(app(ChannelEventHandler::class));

    $conversation = $earlier->fresh();
    expect($conversation->contact_name)->toBe('Sara')
        ->and($conversation->contact->name)->toBe('Sara')
        ->and($conversation->contact->last_active_at->isSameMinute(now()))->toBeTrue()
        ->and(Conversation::where('channel_id', $channel->id)->count())->toBe(1);
});
