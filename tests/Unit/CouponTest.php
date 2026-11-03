<?php

use App\Models\Coupon;
use App\Models\Plan;

function couponOf(array $attributes = [], array $plans = []): Coupon
{
    return (new Coupon(['code' => 'BLEX20', 'percent_off' => 20, 'active' => true, ...$attributes]))
        ->setRelation('plans', collect($plans));
}

function paidPlan(array $attributes = []): Plan
{
    return tap(new Plan(['monthly_price' => 39, 'annual_price' => 29, 'trial_days' => 0, ...$attributes]), fn (Plan $plan) => $plan->id = $attributes['id'] ?? 1);
}

it('stores codes upper-case and trimmed', function () {
    expect(couponOf(['code' => ' blex20 '])->code)->toBe('BLEX20');
});

it('takes the percentage off to the cent', function () {
    expect(couponOf()->quote(paidPlan(['monthly_price' => 17]), 'monthly'))
        ->toBe(['list_price' => 17, 'discount' => 3.4, 'price' => 13.6, 'billed_total' => 13.6])
        ->and(couponOf(['percent_off' => 15])->discountCents(1999))->toBe(300)
        ->and(couponOf(['percent_off' => 100])->discountCents(3900))->toBe(3900);
});

it('prices the annual price per month and bills twelve months', function () {
    expect(couponOf()->quote(paidPlan(), 'annual'))
        ->toBe(['list_price' => 29, 'discount' => 5.8, 'price' => 23.2, 'billed_total' => 278.4]);
});

it('applies to any paid plan without a trial while active and in date', function () {
    expect(couponOf(['expires_at' => now()->addDay(), 'usage_limit' => 5])->rejectionFor(paidPlan()))->toBeNull();
});

it('names why a coupon does not apply', function (array $attributes, array $planAttributes, string $expected) {
    $coupon = couponOf($attributes);

    expect($coupon->rejectionFor(paidPlan($planAttributes)))->toBe($expected);
})->with([
    'switched off' => [['active' => false], [], Coupon::INVALID],
    'expired' => [['expires_at' => now()->subSecond()], [], Coupon::EXPIRED],
    'used up' => [['usage_limit' => 0], [], Coupon::EXPIRED],
    'free plan' => [[], ['monthly_price' => 0], Coupon::NOT_FOR_PLAN],
    'trial plan' => [[], ['trial_days' => 14], Coupon::NOT_FOR_PLAN],
]);

it('applies only to the plans it is limited to', function () {
    $pro = paidPlan(['id' => 1]);
    $business = paidPlan(['id' => 2]);

    expect(couponOf([], [$business])->rejectionFor($pro))->toBe(Coupon::NOT_FOR_PLAN)
        ->and(couponOf([], [$pro])->rejectionFor($pro))->toBeNull();
});

it('computes the most an annual plan saves, ignoring Free', function () {
    expect(Plan::annualSavingUpTo([
        new Plan(['monthly_price' => 0, 'annual_price' => 0]),
        new Plan(['monthly_price' => 17, 'annual_price' => 14]),
        new Plan(['monthly_price' => 99, 'annual_price' => 69]),
    ]))->toBe(30)
        ->and(Plan::annualSavingUpTo([]))->toBe(0);
});
