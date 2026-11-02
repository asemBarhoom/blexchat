<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
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
            'status' => 'active',
        ];
    }

    public function trialing(int $daysLeft = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays($daysLeft),
            'trial_used_at' => now(),
        ]);
    }

    public function trialEnded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->subDay(),
            'trial_used_at' => now()->subDays(15),
        ]);
    }
}
