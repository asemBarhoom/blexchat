<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SurveyQuestion;

it('is on a trial only while trialing and before the end date', function () {
    expect((new Subscription(['status' => 'trialing', 'trial_ends_at' => now()->addHour()]))->onTrial())->toBeTrue()
        ->and((new Subscription(['status' => 'trialing', 'trial_ends_at' => now()->subSecond()]))->onTrial())->toBeFalse()
        ->and((new Subscription(['status' => 'active', 'trial_ends_at' => now()->addHour()]))->onTrial())->toBeFalse();
});

it('marks a trial that ran out as ended, but not a plan that never had one', function () {
    expect((new Subscription(['status' => 'trialing', 'trial_ends_at' => now()->subSecond()]))->trialEnded())->toBeTrue()
        ->and((new Subscription(['status' => 'active']))->trialEnded())->toBeFalse();
});

it('counts trial days up, the way the frontend does', function () {
    $this->travelTo(now()->startOfMinute());

    expect((new Subscription(['status' => 'trialing', 'trial_ends_at' => now()->addDays(14)]))->trialDaysLeft())->toBe(14)
        ->and((new Subscription(['status' => 'trialing', 'trial_ends_at' => now()->addHours(25)]))->trialDaysLeft())->toBe(2)
        ->and((new Subscription(['status' => 'trialing', 'trial_ends_at' => now()->addMinute()]))->trialDaysLeft())->toBe(1)
        ->and((new Subscription(['status' => 'active']))->trialDaysLeft())->toBeNull();
});

it('treats a plan as free only when it costs nothing a month', function () {
    expect((new Plan(['monthly_price' => 0]))->isFree())->toBeTrue()
        ->and((new Plan(['monthly_price' => 17]))->isFree())->toBeFalse()
        ->and((new Plan(['trial_days' => 14]))->hasTrial())->toBeTrue()
        ->and((new Plan(['trial_days' => 0]))->hasTrial())->toBeFalse();
});

it('allows one answer for a radio question and several otherwise', function (string $kind, bool $several) {
    expect((new SurveyQuestion(['kind' => $kind]))->allowsSeveral())->toBe($several);
})->with([
    'radio' => ['radio', false],
    'multi' => ['multi', true],
    'grid' => ['grid', true],
]);
