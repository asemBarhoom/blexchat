<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.meta.app_secret' => 'meta-secret', 'services.meta.webhook_verify_token' => 'verify-me', 'services.channels.driver' => 'meta']);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->webhook = function (string $platform, array $payload) {
        $body = json_encode($payload);

        return $this->call('POST', "/api/v1/webhooks/{$platform}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'meta-secret'),
        ], $body);
    };
});

it('puts a WhatsApp message in the inbox once, with the sender’s name and number', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'whatsapp', 'account_id' => '1234567890', 'status' => 'active']);
    $payload = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
        'metadata' => ['phone_number_id' => '1234567890'],
        'contacts' => [['wa_id' => '966501234567', 'profile' => ['name' => 'أم فيصل']]],
        'messages' => [['from' => '966501234567', 'id' => 'wamid.1', 'timestamp' => '1767225600', 'type' => 'text', 'text' => ['body' => 'هل التوصيل متاح؟']]],
    ]]]]]];

    ($this->webhook)('whatsapp', $payload)->assertOk();
    ($this->webhook)('whatsapp', $payload)->assertOk();

    $conversation = Conversation::where('channel_id', $channel->id)->sole();
    expect($conversation->only('contact_name', 'last_message', 'unread_count'))->toBe(['contact_name' => 'أم فيصل', 'last_message' => 'هل التوصيل متاح؟', 'unread_count' => 1])
        ->and($conversation->contact->only('name', 'phone', 'platform'))->toBe(['name' => 'أم فيصل', 'phone' => '+966501234567', 'platform' => 'whatsapp'])
        ->and($conversation->messages()->count())->toBe(1);
});

it('puts a Messenger message in the inbox and skips the Page’s own echoes', function () {
    $channel = Channel::factory()->for($this->owner)->create(['platform' => 'messenger', 'account_id' => 'page-1', 'status' => 'active']);

    ($this->webhook)('messenger', ['object' => 'page', 'entry' => [['id' => 'page-1', 'messaging' => [
        ['sender' => ['id' => 'psid-9'], 'timestamp' => 1767225600000, 'message' => ['mid' => 'm.1', 'text' => 'Open today?']],
        ['sender' => ['id' => 'page-1'], 'timestamp' => 1767225600000, 'message' => ['mid' => 'm.2', 'text' => 'Yes!', 'is_echo' => true]],
    ]]]])->assertOk();

    expect(Conversation::where('channel_id', $channel->id)->sole()->messages()->pluck('content')->all())->toBe(['Open today?']);
});

it('refuses an unsigned webhook and answers Meta’s verification', function () {
    $this->postJson('/api/v1/webhooks/whatsapp', ['object' => 'whatsapp_business_account'])->assertForbidden();

    $this->get('/api/v1/webhooks/messenger?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertOk()->assertSee('42');
    $this->get('/api/v1/webhooks/messenger?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
});

it('sends an inbox reply through the platform’s API', function (string $platform, string $url, array $body) {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']], 'message_id' => 'm.out'])]);
    $channel = Channel::factory()->for($this->owner)->create(['platform' => $platform, 'account_id' => '1234567890', 'access_token' => 'channel-token', 'status' => 'active']);
    $conversation = Conversation::factory()->for($channel)->create(['contact_platform_id' => '966501234567']);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['type' => 'text_with_button', 'content' => 'Yes, 2–4 days', 'button_label' => 'Order', 'button_url' => 'https://shop.test'])
        ->assertCreated();

    Http::assertSent(fn (Request $request): bool => $request->url() === $url
        && $request->hasHeader('Authorization', 'Bearer channel-token')
        && array_intersect_key($request->data(), $body) == $body);
})->with([
    'whatsapp' => ['whatsapp', 'https://graph.facebook.com/v21.0/1234567890/messages', ['to' => '966501234567', 'text' => ['body' => "Yes, 2–4 days\n\nOrder: https://shop.test"]]],
    'messenger' => ['messenger', 'https://graph.facebook.com/v21.0/me/messages', ['recipient' => ['id' => '966501234567'], 'message' => ['text' => "Yes, 2–4 days\n\nOrder: https://shop.test"]]],
]);

it('says a TikTok reply cannot be sent yet, and keeps nothing', function () {
    $conversation = Conversation::factory()->for(Channel::factory()->for($this->owner)->create(['platform' => 'tiktok']))->create();

    $this->actingAs($this->owner)
        ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['type' => 'text', 'content' => 'Hi'])
        ->assertStatus(502);

    expect($conversation->messages()->count())->toBe(0);
});

it('simulates and answers messages on the fake driver', function (string $platform) {
    config(['services.channels.driver' => 'fake']);
    $channel = Channel::factory()->for($this->owner)->create(['platform' => $platform, 'status' => 'active']);

    $conversation = $this->actingAs($this->owner)
        ->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'message', 'text' => 'Hello', 'from' => 'lulu'])
        ->assertCreated()
        ->json('data.conversation_id');

    $this->postJson("/api/v1/conversations/{$conversation}/messages", ['type' => 'text', 'content' => 'Hi Lulu'])->assertCreated();
    expect(Conversation::find($conversation)->messages()->pluck('content')->all())->toBe(['Hello', 'Hi Lulu']);
})->with(['whatsapp', 'messenger', 'tiktok']);
