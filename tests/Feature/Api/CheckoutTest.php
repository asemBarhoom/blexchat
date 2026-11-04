<?php

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CheckoutSeeder;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, CheckoutSeeder::class]);
});

describe('GET /api/v1/checkout', function () {
    it('serves the tiers, the trial reminder day and the annual saving without signing in', function () {
        $this->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonPath('data.plans.*.id', ['essential', 'pro', 'business'])
            ->assertJsonPath('data.trial_reminder_days', 7)
            ->assertJsonPath('data.annual_saving_up_to', 30)
            ->assertJsonPath('data.coupons_on_trial', false);
    });

    it('follows the admin settings', function () {
        Plan::where('slug', 'business')->update(['in_checkout' => false]);
        Plan::where('slug', 'pro')->update(['active' => false]);
        Setting::put(Setting::TRIAL_REMINDER_DAYS, 10);

        $this->getJson('/api/v1/checkout?lang=ar')
            ->assertJsonPath('data.plans.*.name', ['Essential'])
            ->assertJsonPath('data.trial_reminder_days', 10);
    });
});

it('adds the computed annual saving to the plan ladder', function () {
    $this->getJson('/api/v1/plans')->assertJsonPath('meta.annual_saving_up_to', 30);
});

describe('POST /api/v1/coupons/check', function () {
    beforeEach(function () {
        Sanctum::actingAs(User::factory()->create());
    });

    it('prices a plan with the code, ignoring case', function () {
        $this->postJson('/api/v1/coupons/check', ['code' => ' blex20 ', 'plan' => 'pro'])
            ->assertOk()
            ->assertExactJson(['data' => [
                'code' => 'BLEX20',
                'percent_off' => 20,
                'plan' => 'pro',
                'billing' => 'monthly',
                'list_price' => 39,
                'discount' => 7.8,
                'price' => 31.2,
                'billed_total' => 31.2,
            ]]);
    });

    it('prices the annual price per month and bills the year', function () {
        $this->postJson('/api/v1/coupons/check', ['code' => 'BLEX20', 'plan' => 'business', 'billing' => 'annual'])
            ->assertOk()
            ->assertJsonPath('data.list_price', 69)
            ->assertJsonPath('data.discount', 13.8)
            ->assertJsonPath('data.price', 55.2)
            ->assertJsonPath('data.billed_total', 662.4);
    });

    it('rejects a code with a stable error code and a translated message', function (Closure $setUp, string $plan, string $code, string $message) {
        $setUp();

        $this->postJson('/api/v1/coupons/check?lang=ar', ['code' => 'BLEX20', 'plan' => $plan])
            ->assertUnprocessable()
            ->assertExactJson(['code' => $code, 'message' => $message]);
    })->with([
        'unknown' => [fn () => Coupon::query()->delete(), 'pro', 'coupon_invalid', 'هذا الكود غير صالح'],
        'switched off' => [fn () => Coupon::query()->update(['active' => false]), 'pro', 'coupon_invalid', 'هذا الكود غير صالح'],
        'expired' => [fn () => Coupon::query()->update(['expires_at' => now()->subMinute()]), 'pro', 'coupon_expired', 'انتهت صلاحية هذا الكود'],
        'used up' => [fn () => Coupon::query()->update(['usage_limit' => 3, 'times_used' => 3]), 'pro', 'coupon_expired', 'هذا الكود لم يعد متاحًا'],
        'on a trial' => [fn () => null, 'essential', 'coupon_not_for_plan', 'هذا الكود لا ينطبق على باقة Essential'],
        'on Free' => [fn () => null, 'free', 'coupon_not_for_plan', 'هذا الكود لا ينطبق على باقة Free'],
        'other plans only' => [fn () => Coupon::firstOrFail()->plans()->sync(Plan::where('slug', 'business')->pluck('id')), 'pro', 'coupon_not_for_plan', 'هذا الكود لا ينطبق على باقة Pro'],
    ]);

    it('accepts a code limited to the chosen plan', function () {
        Coupon::firstOrFail()->plans()->sync(Plan::where('slug', 'pro')->pluck('id'));

        $this->postJson('/api/v1/coupons/check', ['code' => 'BLEX20', 'plan' => 'pro'])->assertOk();
    });

    it('validates the plan and billing period', function () {
        $this->postJson('/api/v1/coupons/check', ['code' => 'BLEX20', 'plan' => 'nope', 'billing' => 'weekly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan', 'billing']);

        $this->postJson('/api/v1/coupons/check', ['plan' => 'pro'])->assertJsonValidationErrors(['code']);
    });
});

it('needs a signed-in owner to check a coupon', function () {
    $this->postJson('/api/v1/coupons/check', ['code' => 'BLEX20', 'plan' => 'pro'])->assertUnauthorized();
});
