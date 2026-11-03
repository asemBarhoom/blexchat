<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Checkout settings as the frontend hard-coded them (src/app/Checkout.tsx and src/app/plans.ts):
 * the BLEX20 coupon, the tiers the switcher offers and the trial reminder day. Run after
 * OnboardingSeeder; safe to run again.
 */
class CheckoutSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::updateOrCreate(['code' => 'BLEX20'], ['percent_off' => 20, 'active' => true]);

        Plan::whereIn('slug', ['essential', 'pro', 'business'])->update(['in_checkout' => true]);

        Setting::firstOrCreate(['key' => Setting::TRIAL_REMINDER_DAYS], ['value' => Setting::DEFAULTS[Setting::TRIAL_REMINDER_DAYS]]);
    }
}
