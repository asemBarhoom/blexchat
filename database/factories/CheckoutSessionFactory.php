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
            'payment_provider' => 'paddle',
            'plan_id' => Plan::factory(),
            'billing_period' => 'monthly',
            'payment_session_id' => 'txn_'.Str::lower(Str::random(26)),
            'url' => 'https://blexchat.test/payments/paddle?_ptxn=txn_example',
            'amount_total' => 3900,
            'currency' => 'usd',
            'status' => 'open',
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'complete',
            'payment_subscription_id' => 'sub_'.Str::random(14),
            'completed_at' => now(),
        ]);
    }
}
