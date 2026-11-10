<?php

use App\Filament\Resources\MessageDeliveryAttemptResource;
use App\Filament\Resources\MessageDeliveryAttemptResource\Pages\ListMessageDeliveryAttempts;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\MessageDeliveryAttempt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
    app()->setLocale('ar');

    /** A failed WhatsApp reply, ready to be retried: [attempt, conversation]. */
    $this->failedWhatsAppAttempt = function (string $content = 'Back in stock'): array {
        config(['services.channels.driver' => 'meta']);

        $channel = Channel::factory()->create(['platform' => 'whatsapp', 'account_id' => '1234567890', 'access_token' => 'token', 'status' => 'active']);
        $conversation = Conversation::factory()->for($channel)->create(['contact_platform_id' => '966501234567']);
        $attempt = MessageDeliveryAttempt::factory()->create(['conversation_id' => $conversation->id, 'content' => $content]);

        return [$attempt, $conversation];
    };
});

it('lists the failed attempts by default and hides the sent ones', function () {
    $failed = MessageDeliveryAttempt::factory()->create(['content' => 'Failed reply']);
    $sent = MessageDeliveryAttempt::factory()->sent()->create(['content' => 'Sent reply']);

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$sent]);

    $this->get('/admin/message-delivery-attempts')
        ->assertOk()
        ->assertSee('محاولات الإرسال');
});

it('filters by platform, failure reason and period', function () {
    $make = fn (string $platform, array $attributes = []): MessageDeliveryAttempt => MessageDeliveryAttempt::factory()->create([
        'conversation_id' => Conversation::factory()->for(Channel::factory()->create(['platform' => $platform]))->create()->id,
        ...$attributes,
    ]);

    $whatsapp = $make('whatsapp');
    $instagram = $make('instagram');
    $dead = $make('tiktok', ['error_code' => 'channel_not_live']);
    $old = $make('messenger', ['last_attempt_at' => now()->subDays(10)]);

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->filterTable('platform', 'whatsapp')
        ->assertCanSeeTableRecords([$whatsapp])
        ->assertCanNotSeeTableRecords([$instagram, $dead]);

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->filterTable('error_code', 'channel_not_live')
        ->assertCanSeeTableRecords([$dead])
        ->assertCanNotSeeTableRecords([$whatsapp]);

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->filterTable('period', ['from' => now()->subDay()->toDateString()])
        ->assertCanSeeTableRecords([$whatsapp])
        ->assertCanNotSeeTableRecords([$old]);
});

it('retries a failed attempt from the list and adds the reply to the conversation', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ok']]])]);
    [$attempt, $conversation] = ($this->failedWhatsAppAttempt)();

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->callAction(TestAction::make('retry')->table($attempt))
        ->assertNotified();

    expect($attempt->fresh()->status)->toBe('sent')
        ->and($attempt->fresh()->attempts)->toBe(2)
        ->and($conversation->messages()->where('content', 'Back in stock')->exists())->toBeTrue();
});

it('keeps the failure and updates its reason when the retry fails again', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'nope']], 500)]);
    [$attempt, $conversation] = ($this->failedWhatsAppAttempt)();
    $attempt->update(['error_message' => 'old reason']);

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->callAction(TestAction::make('retry')->table($attempt))
        ->assertNotified();

    expect($attempt->fresh()->status)->toBe('failed')
        ->and($attempt->fresh()->error_code)->toBe('provider_error')
        ->and($attempt->fresh()->error_message)->not->toBe('old reason')
        ->and($attempt->fresh()->attempts)->toBe(2)
        ->and($conversation->messages()->count())->toBe(0);
});

it('hides retry on sent attempts and disables it when the channel cannot send', function () {
    $sent = MessageDeliveryAttempt::factory()->sent()->create();
    $dead = MessageDeliveryAttempt::factory()->channelNotLive()->create();
    $retryable = MessageDeliveryAttempt::factory()->create();

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->filterTable('status', 'sent')
        ->assertActionHidden(TestAction::make('retry')->table($sent));

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->assertActionDisabled(TestAction::make('retry')->table($dead))
        ->assertActionEnabled(TestAction::make('retry')->table($retryable));
});

it('retries the selected attempts in bulk', function () {
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['messages' => [['id' => 'wamid.first']]])
        ->push(['messages' => [['id' => 'wamid.second']]])]);
    [$first, $firstConversation] = ($this->failedWhatsAppAttempt)('First reply');
    [$second, $secondConversation] = ($this->failedWhatsAppAttempt)('Second reply');

    Livewire::test(ListMessageDeliveryAttempts::class)
        ->selectTableRecords([$first, $second])
        ->callAction(TestAction::make('retrySelected')->table()->bulk())
        ->assertNotified();

    expect($first->fresh()->status)->toBe('sent')
        ->and($second->fresh()->status)->toBe('sent')
        ->and($firstConversation->messages()->count())->toBe(1)
        ->and($secondConversation->messages()->count())->toBe(1);
});

it('shows the full text, the error message and the conversation link on the attempt page', function () {
    $attempt = MessageDeliveryAttempt::factory()->create([
        'content' => 'A long reply that failed',
        'error_message' => 'Messenger refused the message',
    ]);

    $this->get('/admin/message-delivery-attempts/'.$attempt->id)
        ->assertOk()
        ->assertSee('A long reply that failed')
        ->assertSee('Messenger refused the message')
        ->assertSee('المحادثة');
});

it('badges the navigation with the failed count', function () {
    MessageDeliveryAttempt::factory()->count(2)->create();
    MessageDeliveryAttempt::factory()->sent()->create();

    expect(MessageDeliveryAttemptResource::getNavigationBadge())->toBe('2');
});

it('shows the failed deliveries of today on the dashboard', function () {
    MessageDeliveryAttempt::factory()->create(['last_attempt_at' => now()]);

    Livewire::test(StatsOverviewWidget::class)
        ->assertSee('رسائل فشل إرسالها اليوم');
});
