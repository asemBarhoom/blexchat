<?php

use App\Jobs\ProcessInstagramWebhook;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.instagram.app_id' => 'app-123',
        'services.instagram.app_secret' => 'secret-xyz',
        'services.instagram.redirect_uri' => 'https://app.blexchat.test/connect/instagram',
        'services.instagram.webhook_verify_token' => 'verify-me',
        'services.instagram.graph_version' => 'v21.0',
    ]);
});

function instagramChannel(): Channel
{
    return Channel::factory()->create([
        'platform' => 'instagram',
        'account_id' => '17840001',
        'username' => 'nourboutique',
        'access_token' => 'IGAA-long-lived',
        'status' => 'active',
    ]);
}

/**
 * @param  list<array<string, mixed>>  $entries
 */
function deliverWebhook(array $entries, ?string $secret = 'secret-xyz'): TestResponse
{
    $body = json_encode(['object' => 'instagram', 'entry' => $entries]);
    $headers = $secret === null ? [] : ['HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret)];

    return test()->call('POST', '/api/v1/webhooks/instagram', [], [], [], [...$headers, 'CONTENT_TYPE' => 'application/json'], $body);
}

/**
 * @return array<string, mixed>
 */
function commentEntry(string $commentId, string $text, string $fromId = '9001', string $username = 'reem.k'): array
{
    return ['id' => '17840001', 'time' => time(), 'changes' => [[
        'field' => 'comments',
        'value' => ['id' => $commentId, 'text' => $text, 'from' => ['id' => $fromId, 'username' => $username], 'media' => ['id' => 'media-1', 'media_product_type' => 'FEED']],
    ]]];
}

/**
 * @param  array<string, mixed>  $message
 * @return array<string, mixed>
 */
function messageEntry(array $message, string $senderId = '5005'): array
{
    return ['id' => '17840001', 'time' => time(), 'messaging' => [[
        'sender' => ['id' => $senderId], 'recipient' => ['id' => '17840001'], 'timestamp' => now()->getTimestampMs(), 'message' => $message,
    ]]];
}

function fakeInstagramSends(): void
{
    Http::fake([
        'graph.instagram.com/v21.0/media-1*' => Http::response(['caption' => 'عباية الشتاء وصلت ✨', 'media_type' => 'IMAGE', 'timestamp' => '2026-10-30T10:00:00+0000']),
        'graph.instagram.com/v21.0/17840001/messages' => Http::response(['recipient_id' => '9001', 'message_id' => 'mid-out-1']),
        'graph.instagram.com/v21.0/*/replies' => Http::response(['id' => 'reply-1']),
        'graph.instagram.com/v21.0/5005*' => Http::response(['username' => 'sara.m', 'profile_pic' => 'https://cdn.example.com/sara.jpg']),
    ]);
}

it('connects an Instagram account through OAuth', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $url = $this->getJson('/api/v1/channels/instagram/authorize')->assertOk()->json('data.url');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://www.instagram.com/oauth/authorize?')
        ->and($query['client_id'])->toBe('app-123')
        ->and($query['scope'])->toContain('instagram_business_manage_messages');

    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-lived', 'user_id' => 123]),
        'graph.instagram.com/access_token*' => Http::response(['access_token' => 'IGAA-long-lived', 'token_type' => 'bearer', 'expires_in' => 5184000]),
        'graph.instagram.com/v21.0/me/subscribed_apps*' => Http::response(['success' => true]),
        'graph.instagram.com/v21.0/me*' => Http::response(['user_id' => '17840001', 'username' => 'nourboutique', 'followers_count' => 14800]),
    ]);

    $this->postJson('/api/v1/channels/instagram', ['code' => 'code-from-instagram', 'state' => $query['state']])
        ->assertCreated()
        ->assertJsonPath('data.platform', 'instagram');

    $channel = Channel::where('account_id', '17840001')->firstOrFail();

    expect($channel->access_token)->toBe('IGAA-long-lived')
        ->and(DB::table('channels')->where('id', $channel->id)->value('access_token'))->not->toBe('IGAA-long-lived')
        ->and($channel->token_expires_at->diffInDays(now(), true))->toBeGreaterThan(59)
        ->and($channel->followers)->toBe(14800);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'subscribed_apps') && str_contains($request->url(), 'comments,messages'));
});

it('connects an Instagram account through Unified Login when a configuration is set', function () {
    config(['services.instagram.config_id' => 'config-777']);
    Sanctum::actingAs(User::factory()->create());

    $url = $this->getJson('/api/v1/channels/instagram/authorize')->assertOk()->json('data.url');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://www.facebook.com/dialog/oauth?')
        ->and($query['client_id'])->toBe('app-123')
        ->and($query['config_id'])->toBe('config-777')
        ->and($query)->not->toHaveKey('scope');

    Http::fake([
        'graph.facebook.com/v21.0/oauth/access_token*' => Http::response(['access_token' => 'BISU-token', 'token_type' => 'bearer']),
        'graph.facebook.com/v21.0/me/assigned_instagram_accounts*' => Http::response(['data' => [['id' => '17840001', 'username' => 'nourboutique']]]),
        'graph.facebook.com/v21.0/17840001/subscribed_apps*' => Http::response(['success' => true]),
    ]);

    $this->postJson('/api/v1/channels/instagram', ['code' => 'code-from-meta', 'state' => $query['state']])->assertCreated();

    $channel = Channel::where('account_id', '17840001')->firstOrFail();

    expect($channel->access_token)->toBe('BISU-token')
        ->and($channel->username)->toBe('nourboutique')
        ->and($channel->token_expires_at->isAfter(now()->addYears(5)))->toBeTrue();
});

it('refuses a Unified Login that shared no Instagram account', function () {
    config(['services.instagram.config_id' => 'config-777']);
    Sanctum::actingAs($user = User::factory()->create());
    $state = Crypt::encryptString(json_encode(['user' => $user->id, 'expires' => now()->addMinutes(10)->timestamp]));

    Http::fake([
        'graph.facebook.com/v21.0/oauth/access_token*' => Http::response(['access_token' => 'BISU-token']),
        'graph.facebook.com/v21.0/me/assigned_instagram_accounts*' => Http::response(['data' => []]),
    ]);

    $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => $state])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'account_ineligible');

    expect(Channel::count())->toBe(0);
});

it('refuses a connection started by another account or too long ago', function () {
    Sanctum::actingAs(User::factory()->create());

    $otherUser = Crypt::encryptString(json_encode(['user' => 999, 'expires' => now()->addMinutes(10)->timestamp]));
    $expired = Crypt::encryptString(json_encode(['user' => auth()->id(), 'expires' => now()->subMinute()->timestamp]));

    $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => $otherUser])->assertJsonValidationErrors(['state']);
    $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => $expired])->assertJsonValidationErrors(['state']);
    $this->postJson('/api/v1/channels/instagram', ['code' => 'c', 'state' => 'tampered'])->assertJsonValidationErrors(['state']);

    Http::assertNothingSent();
});

it('reports when Instagram refuses the connection', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $state = Crypt::encryptString(json_encode(['user' => $user->id, 'expires' => now()->addMinutes(10)->timestamp]));

    // Instagram's OAuth endpoint answers some failures with JSON and others with a plain-text body.
    Http::fake(['api.instagram.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Invalid authorization code']], 400)
        ->push('bad request', 400)]);

    $this->postJson('/api/v1/channels/instagram', ['code' => 'used-code', 'state' => $state])
        ->assertStatus(502)
        ->assertJsonPath('error', 'Invalid authorization code');

    $this->postJson('/api/v1/channels/instagram', ['code' => 'used-code', 'state' => $state])
        ->assertStatus(502)
        ->assertJsonPath('error', 'bad request');

    expect(Channel::count())->toBe(0);
});

it('answers Meta webhook verification only with the right token', function () {
    $this->get('/api/v1/webhooks/instagram?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=1158201444')
        ->assertOk()
        ->assertSee('1158201444');

    $this->get('/api/v1/webhooks/instagram?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')->assertForbidden();
});

it('rejects webhook events without a valid signature', function () {
    Queue::fake();

    deliverWebhook([commentEntry('c1', 'السعر')], secret: null)->assertForbidden();
    deliverWebhook([commentEntry('c1', 'السعر')], secret: 'forged')->assertForbidden();
    deliverWebhook([commentEntry('c1', 'السعر')])->assertOk();

    Queue::assertPushed(ProcessInstagramWebhook::class, 1);
});

it('replies under a matching comment and sends the commenter a DM with a button, once', function () {
    $channel = instagramChannel();
    $automation = Automation::factory()->for($channel->user)->for($channel)->create([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['السعر', 'price'],
        'actions' => [
            ['type' => 'reply_comment', 'message' => 'أرسلنا لك التفاصيل بالخاص 💌'],
            ['type' => 'send_dm_with_button', 'message' => 'هلا والله! 👋 السعر ٢٤٩ ريال', 'button_label' => 'شوفي المنتج', 'button_url' => 'https://nourboutique.com/abaya-winter'],
        ],
    ]);
    fakeInstagramSends();

    deliverWebhook([commentEntry('c1', 'كم السعر لو سمحتي؟ 😍')])->assertOk();
    deliverWebhook([commentEntry('c1', 'كم السعر لو سمحتي؟ 😍')])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/c1/replies') && $request['message'] === 'أرسلنا لك التفاصيل بالخاص 💌');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/17840001/messages')
        && $request['recipient'] === ['comment_id' => 'c1']
        && str_starts_with(data_get($request->data(), 'message.attachment.payload.buttons.0.url'), url('/l/'))
        && $request->hasHeader('Authorization', 'Bearer IGAA-long-lived'));
    Http::assertSentCount(3);

    $comment = Comment::where('platform_comment_id', 'c1')->firstOrFail();
    expect($comment->automated_reply)->toBeTrue()
        ->and($comment->automation_id)->toBe($automation->id)
        ->and($comment->post->caption)->toBe('عباية الشتاء وصلت ✨');

    $dm = Message::where('automated', true)->firstOrFail();
    expect($dm->platform_message_id)->toBe('mid-out-1')
        ->and($dm->type)->toBe('text_with_button')
        ->and($dm->conversation->contact_name)->toBe('reem.k');

    // The button goes through the click counter, which sends the customer on to the shop.
    $this->get(Message::trackedUrl($dm->link_token))->assertRedirect('https://nourboutique.com/abaya-winter');
    expect($dm->refresh()->link_clicks)->toBe(1)->and($dm->first_clicked_at)->not->toBeNull();
});

it('leaves comments alone when nothing matches or the shop wrote them', function () {
    $channel = instagramChannel();
    Automation::factory()->for($channel->user)->for($channel)->create(['trigger_type' => 'comment_keyword', 'keywords' => ['السعر']]);
    fakeInstagramSends();

    deliverWebhook([commentEntry('c2', 'تجنن ما شاء الله 💜')]);
    deliverWebhook([commentEntry('c3', 'السعر في الخاص', fromId: '17840001', username: 'nourboutique')]);

    expect(Comment::where('platform_comment_id', 'c2')->value('automated_reply'))->toBeFalse()
        ->and(Comment::where('platform_comment_id', 'c3')->exists())->toBeFalse();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'replies') || str_contains($request->url(), 'messages'));
});

it('answers only comments on the post a wizard automation watches', function () {
    $channel = instagramChannel();
    $other = Post::factory()->create(['channel_id' => $channel->id, 'posted_at' => '2026-10-01 00:00:00']);
    $specific = Automation::factory()->for($channel->user)->for($channel)->create([
        'trigger_type' => 'comment_keyword', 'keywords' => ['السعر'], 'post_mode' => 'specific', 'post_id' => $other->id,
    ]);
    fakeInstagramSends();

    deliverWebhook([commentEntry('c4', 'السعر؟')]);
    expect(Comment::where('platform_comment_id', 'c4')->value('automated_reply'))->toBeFalse();

    $specific->forceFill(['post_mode' => 'next', 'post_id' => null, 'created_at' => '2026-10-29 00:00:00'])->save();
    deliverWebhook([commentEntry('c5', 'السعر؟')]);
    expect(Comment::where('platform_comment_id', 'c5')->value('automation_id'))->toBe($specific->id);
});

it('greets the first DM, ignores its own echoes, and answers story replies', function () {
    $channel = instagramChannel();
    Automation::factory()->for($channel->user)->for($channel)->create([
        'trigger_type' => 'dm_keyword', 'keywords' => [],
        'actions' => [['type' => 'send_dm', 'message' => 'هلا والله! 👋 وصلتنا رسالتك']],
    ]);
    Automation::factory()->for($channel->user)->for($channel)->create([
        'trigger_type' => 'story_mention', 'keywords' => [],
        'actions' => [['type' => 'send_dm', 'message' => 'يسعدنا ردّك 💜']],
    ]);
    fakeInstagramSends();

    deliverWebhook([messageEntry(['mid' => 'm1', 'text' => 'السلام عليكم'])]);
    deliverWebhook([messageEntry(['mid' => 'm2', 'text' => 'عندكم مقاس ٥٨؟'])]);
    deliverWebhook([messageEntry(['mid' => 'm3', 'text' => 'reply', 'is_echo' => true], senderId: '17840001')]);
    deliverWebhook([messageEntry(['mid' => 'm4', 'text' => 'كم سعرها؟ 😍', 'reply_to' => ['story' => ['id' => 's1', 'url' => 'https://cdn.example.com/s1']]])]);

    $conversation = Conversation::where('platform_conversation_id', '5005')->firstOrFail();
    $sent = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/17840001/messages'))->values();

    expect($sent)->toHaveCount(2)
        ->and($sent[0][0]['message'])->toBe(['text' => 'هلا والله! 👋 وصلتنا رسالتك'])
        ->and($sent[1][0]['message'])->toBe(['text' => 'يسعدنا ردّك 💜'])
        ->and($sent[0][0]['recipient'])->toBe(['id' => '5005'])
        ->and($conversation->contact_name)->toBe('sara.m')
        ->and($conversation->messages()->where('direction', 'inbound')->count())->toBe(3)
        ->and($conversation->unread_count)->toBe(3);
});

it('sends inbox replies through Instagram and reports when Instagram refuses', function () {
    $channel = instagramChannel();
    $conversation = Conversation::factory()->for($channel)->create(['contact_platform_id' => '5005']);
    Sanctum::actingAs($channel->user);

    Http::fake(['graph.instagram.com/v21.0/17840001/messages' => Http::sequence()
        ->push(['message_id' => 'mid-agent-1'])
        ->push(['error' => ['message' => 'This message is sent outside of allowed window.']], 400)]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['type' => 'text', 'content' => 'أبشري، مقاس ٥٨ متوفر'])
        ->assertCreated();

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['type' => 'text', 'content' => 'متابعة'])
        ->assertStatus(502)
        ->assertJsonPath('error', 'This message is sent outside of allowed window.');

    expect(Message::where('platform_message_id', 'mid-agent-1')->value('content'))->toBe('أبشري، مقاس ٥٨ متوفر')
        ->and(Message::where('content', 'متابعة')->exists())->toBeFalse();
});

it('renews expiring tokens and flags channels that must reconnect', function () {
    $expiring = instagramChannel();
    $expiring->update(['token_expires_at' => now()->addDays(3)]);
    $revoked = Channel::factory()->create(['platform' => 'instagram', 'account_id' => '17840002', 'access_token' => 'revoked', 'status' => 'active', 'token_expires_at' => now()->addDay()]);
    $fresh = Channel::factory()->create(['platform' => 'instagram', 'account_id' => '17840003', 'access_token' => 'fresh', 'status' => 'active', 'token_expires_at' => now()->addDays(40)]);

    Http::fake(['graph.instagram.com/refresh_access_token*' => function (Request $request) {
        return $request['access_token'] === 'revoked'
            ? Http::response(['error' => ['message' => 'Session has been invalidated']], 400)
            : Http::response(['access_token' => 'IGAA-renewed', 'expires_in' => 5184000]);
    }]);

    $this->artisan('instagram:refresh-tokens')->assertSuccessful();

    expect($expiring->fresh()->access_token)->toBe('IGAA-renewed')
        ->and($revoked->fresh()->status)->toBe('expired')
        ->and($fresh->fresh()->access_token)->toBe('fresh');
});
