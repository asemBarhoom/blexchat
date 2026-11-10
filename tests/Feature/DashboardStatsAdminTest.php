<?php

use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('counts AI replies, handovers, channels by platform and paid subscriptions', function () {
    Channel::query()->delete();
    Subscription::query()->delete();

    $channel = Channel::factory()->create(['platform' => 'whatsapp']);
    $conversation = Conversation::factory()->for($channel)->create();
    $conversation->messages()->create(['direction' => 'outbound', 'type' => 'text', 'content' => 'Hi', 'automated' => true, 'by_ai' => true, 'sent_at' => now()]);
    $conversation->handOver('unmatched');

    Subscription::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'status' => 'active']);
    Subscription::factory()->create(['plan_id' => Plan::free()->id, 'status' => 'active']);
    Subscription::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'status' => 'trialing', 'trial_ends_at' => now()->addDays(5)]);

    Livewire::test(StatsOverviewWidget::class)
        ->assertSee('ردود Blexchat AI اليوم')
        ->assertSee('تحويلات لموظف اليوم')
        ->assertSee('Instagram 0 · WhatsApp 1 · Messenger 0 · TikTok 0')
        ->assertSee('تجارب شغّالة: 1');

    expect((fn (): int => $this->paidSubscriptions())->call(new StatsOverviewWidget))->toBe(1);
});
