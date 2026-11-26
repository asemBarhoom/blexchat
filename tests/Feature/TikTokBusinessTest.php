<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.tiktok.client_id' => 'tiktok-app-id',
        'services.tiktok.client_secret' => 'tiktok-secret',
        'services.tiktok.auth_url' => 'https://www.tiktok.com/v2/auth/authorize?client_key=tiktok-app-id',
        'services.channels.driver' => 'meta',
    ]);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create(['platform' => 'tiktok', 'account_id' => 'tt-open-id', 'access_token' => 'tt-access', 'token_expires_at' => now()->addDay(), 'status' => 'active']);
    $this->webhook = function (string $event, array $content, ?int $signedAt = null, string $secret = 'tiktok-secret') {
        $body = json_encode(['client_key' => 'tiktok-app-id', 'event' => $event, 'create_time' => 1767225600, 'user_openid' => 'tt-open-id', 'content' => json_encode($content)]);
        $signedAt ??= now()->timestamp;

        return $this->call('POST', '/api/v1/webhooks/tiktok', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_TIKTOK_SIGNATURE' => "t={$signedAt},s=".hash_hmac('sha256', "{$signedAt}.{$body}", $secret),
        ], $body);
    };
    $this->message = fn (string $text, string $messageId = 'tt-msg-in-1', string $role = 'personal_account'): array => [
        'from' => 'sara.styles', 'to' => 'nourboutique', 'conversation_id' => 'tt-conversation-1', 'message_id' => $messageId,
        'timestamp' => 1767225600000, 'type' => 'text', 'text' => ['body' => $text],
        'from_user' => ['id' => 'tt-person-1', 'role' => $role], 'to_user' => ['id' => 'tt-open-id', 'role' => 'business_account'],
    ];
});

it('puts a TikTok message in the inbox once, however often TikTok delivers it, and answers it in its conversation', function () {
    Http::fake(['business-api.tiktok.com/open_api/v1.3/business/message/send/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['message' => ['message_id' => 'tt-msg-out-1']]])]);
    Automation::factory()->for($this->owner)->for($this->channel)->create([
        'trigger_type' => 'dm_keyword',
        'keywords' => ['price'],
        'actions' => [['type' => 'send_dm', 'message' => 'Here is the price list.']],
    ]);

    ($this->webhook)('im_receive_msg', ($this->message)('What is the price?'))->assertOk();
    // A retry of the same event comes with a new signature.
    $this->travel(30)->seconds();
    ($this->webhook)('im_receive_msg', ($this->message)('What is the price?'))->assertOk();

    $conversation = Conversation::where('channel_id', $this->channel->id)->sole();
    expect($conversation->only('platform_conversation_id', 'contact_platform_id', 'contact_name'))->toBe(['platform_conversation_id' => 'tt-conversation-1', 'contact_platform_id' => 'tt-person-1', 'contact_name' => 'sara.styles'])
        ->and($conversation->messages()->where('direction', 'inbound')->pluck('content')->all())->toBe(['What is the price?'])
        ->and($conversation->messages()->where('direction', 'outbound')->sole()->platform_message_id)->toBe('tt-msg-out-1');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Access-Token', 'tt-access')
        && $request['business_id'] === 'tt-open-id' && $request['recipient'] === 'tt-conversation-1' && str_starts_with($request['text']['body'], 'Here is the price list.'));
});

it('leaves the account’s own messages, echoed back by TikTok, out of the inbox', function () {
    ($this->webhook)('im_receive_msg', ($this->message)('Thanks for your order!', role: 'business_account'))->assertOk();

    expect(Message::count())->toBe(0);
});

it('refuses a webhook with no signature, the wrong secret, or an old timestamp', function () {
    $this->call('POST', '/api/v1/webhooks/tiktok', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['event' => 'im_receive_msg']))->assertForbidden();
    ($this->webhook)('im_receive_msg', ($this->message)('hi'), secret: 'someone-else')->assertForbidden();
    ($this->webhook)('im_receive_msg', ($this->message)('hi'), signedAt: now()->subMinutes(10)->timestamp)->assertForbidden();

    expect(Message::count())->toBe(0);
});

it('answers a new TikTok comment publicly, sends no private message, and leaves replies alone', function () {
    Http::fake(['business-api.tiktok.com/open_api/v1.3/business/comment/reply/create/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['comment_id' => '7247303576418566999']])]);
    Automation::factory()->for($this->owner)->for($this->channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['price'],
        'actions' => [
            ['type' => 'reply_comment', 'message' => 'Message us “price” and we’ll send it 💌'],
            ['type' => 'send_dm', 'message' => 'Here is the price list.'],
        ],
    ]);
    // TikTok's ids are 19 digits: sent as JSON numbers, they must not be rounded.
    $comment = ['comment_id' => 7247303576418566913, 'video_id' => 7203946942097902849, 'comment_type' => 'comment', 'comment_action' => 'insert', 'unique_identifier' => '+ABc1D2', 'text' => 'price?', 'timestamp' => 1767225600];

    ($this->webhook)('comment.update', $comment)->assertOk();
    ($this->webhook)('comment.update', [...$comment, 'comment_id' => 7247303576418566999, 'comment_type' => 'reply', 'parent_comment_id' => 7247303576418566913, 'text' => 'Message us “price”'])->assertOk();

    expect(Comment::sole())
        ->platform_comment_id->toBe('7247303576418566913')
        ->automated_reply->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'business/comment/reply/create/')
        && $request->data() === ['business_id' => 'tt-open-id', 'video_id' => '7203946942097902849', 'comment_id' => '7247303576418566913', 'text' => 'Message us “price” and we’ll send it 💌']);
});

it('subscribes the app’s message and comment webhooks to this API', function () {
    Http::fake(['business-api.tiktok.com/open_api/v1.3/business/webhook/update/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => []])]);

    $this->artisan('tiktok:subscribe-webhooks')->assertSuccessful();

    foreach (['DIRECT_MESSAGE', 'COMMENT'] as $eventType) {
        Http::assertSent(fn (Request $request): bool => $request->data() === ['app_id' => 'tiktok-app-id', 'secret' => 'tiktok-secret', 'event_type' => $eventType, 'callback_url' => route('webhooks.tiktok')]);
    }
});

it('does not subscribe the webhooks before TikTok is set up', function () {
    config(['services.tiktok.auth_url' => null]);

    $this->artisan('tiktok:subscribe-webhooks')->assertFailed();

    Http::assertNothingSent();
});
