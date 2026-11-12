<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.instagram.driver' => 'fake']);
    Plan::factory()->free()->create();
});

function runBrandingAutomation(User $owner, string $locale = 'en'): string
{
    $owner->locale = $locale;
    $owner->save();
    $channel = Channel::factory()->for($owner)->create(['platform' => 'instagram', 'status' => 'active']);
    $automation = Automation::factory()->for($owner)->for($channel)->create([
        'trigger_type' => 'dm_keyword',
        'keywords' => ['hello'],
        'actions' => [['type' => 'send_dm', 'message' => 'Hello there']],
    ]);

    test()->actingAs($owner)
        ->postJson("/api/v1/channels/{$channel->id}/test-events", ['type' => 'message', 'text' => 'hello', 'from' => 'customer'])
        ->assertCreated();

    return $channel->conversations()->firstOrFail()->messages()->where('automation_id', $automation->id)->value('content');
}

it('adds the English branding line to Free automated private messages', function (): void {
    $content = runBrandingAutomation(User::factory()->create());

    expect($content)->toBe("Hello there\n\n⚡ Sent with Blexchat");
});

it('uses the account language for Arabic branding', function (): void {
    $content = runBrandingAutomation(User::factory()->create(), 'ar');

    expect($content)->toBe("Hello there\n\n⚡ أُرسلت عبر Blexchat");
});

it('does not brand paid accounts or trials', function (): void {
    $paid = User::factory()->create();
    $paidPlan = Plan::factory()->create(['monthly_price' => 20, 'trial_days' => 0]);
    Subscription::factory()->for($paid)->for($paidPlan)->create();
    expect(runBrandingAutomation($paid))->toBe('Hello there');

    $trial = User::factory()->create();
    $trialPlan = Plan::factory()->create(['monthly_price' => 20, 'trial_days' => 14]);
    Subscription::factory()->trialing()->for($trial)->for($trialPlan)->create();
    expect(runBrandingAutomation($trial))->toBe('Hello there');
});

it('keeps the branding line idempotent', function (): void {
    $owner = User::factory()->create();
    $content = runBrandingAutomation($owner);
    expect(substr_count($content, 'Sent with Blexchat'))->toBe(1);
    expect($owner->sendsBranding())->toBeTrue();
});
