<?php

namespace Database\Factories;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'billing_period' => 'monthly',
            'stripe_session_id' => 'cs_test_'.Str::random(24),
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_example',
            'amount_total' => 3900,
            'currency' => 'usd',
            'status' => 'open',
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'complete',
            'stripe_subscription_id' => 'sub_'.Str::random(14),
            'completed_at' => now(),
        ]);
    }
}
