<?php

use App\Filament\Pages\ManageCheckoutSettings;
use App\Filament\Resources\CouponResource\Pages\ManageCoupons;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CheckoutSeeder;
use Database\Seeders\OnboardingSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, CheckoutSeeder::class]);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a coupon', function () {
    $pro = Plan::where('slug', 'pro')->firstOrFail();

    Livewire::test(ManageCoupons::class)
        ->callAction(CreateAction::class, [
            'code' => 'spring15',
            'percent_off' => 15,
            'plans' => [$pro->id],
            'expires_at' => now()->addMonth()->toDateTimeString(),
            'usage_limit' => 100,
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $coupon = Coupon::findByCode('SPRING15');
    expect($coupon->code)->toBe('SPRING15')
        ->and($coupon->usage_limit)->toBe(100)
        ->and($coupon->plans->pluck('slug')->all())->toBe(['pro']);

    Livewire::test(ManageCoupons::class)
        ->callAction(TestAction::make('edit')->table($coupon), ['percent_off' => 25])
        ->assertHasNoFormErrors();
    expect($coupon->fresh()->percent_off)->toBe(25);

    Livewire::test(ManageCoupons::class)->callAction(TestAction::make('delete')->table($coupon));
    expect(Coupon::find($coupon->id))->toBeNull();
});

it('refuses a code already taken in another case, and a percentage over 100', function () {
    Livewire::test(ManageCoupons::class)
        ->callAction(CreateAction::class, ['code' => 'blex20', 'percent_off' => 120])
        ->assertHasFormErrors(['code', 'percent_off']);
});

it('edits the checkout tiers and the trial reminder day', function () {
    $advanced = Plan::where('slug', 'advanced')->firstOrFail();
    $pro = Plan::where('slug', 'pro')->firstOrFail();

    Livewire::test(ManageCheckoutSettings::class)
        ->assertSchemaStateSet(['trial_reminder_days' => 7])
        ->fillForm(['checkout_plans' => [$pro->id, $advanced->id], 'trial_reminder_days' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get(Setting::TRIAL_REMINDER_DAYS))->toBe(5)
        ->and(Plan::where('in_checkout', true)->orderBy('sort')->pluck('slug')->all())->toBe(['pro', 'advanced']);

    $this->getJson('/api/v1/checkout')
        ->assertJsonPath('data.plans.*.id', ['pro', 'advanced'])
        ->assertJsonPath('data.trial_reminder_days', 5);
});

it('needs at least one tier and a reminder day', function () {
    Livewire::test(ManageCheckoutSettings::class)
        ->fillForm(['checkout_plans' => [], 'trial_reminder_days' => 0])
        ->call('save')
        ->assertHasFormErrors(['checkout_plans', 'trial_reminder_days']);
});
