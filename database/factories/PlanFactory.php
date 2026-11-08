<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monthly = fake()->numberBetween(10, 200);

        return [
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => ucfirst(fake()->word())],
            'tagline' => ['en' => fake()->sentence()],
            'monthly_price' => $monthly,
            'annual_price' => (int) round($monthly * 0.75),
            'contacts_limit' => fake()->randomElement([250, 2500, 7500]),
            'has_ai' => false,
            'has_email_capture' => false,
            'teammates_limit' => 1,
            'has_assignment' => false,
            'features' => ['en' => [fake()->sentence(3), fake()->sentence(3)]],
            'trial_days' => 0,
            'is_recommended' => false,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => Plan::FREE_SLUG,
            'name' => ['en' => 'Free'],
            'monthly_price' => 0,
            'annual_price' => 0,
            'contacts_limit' => 25,
            'live_automations_limit' => 3,
        ]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => ['trial_days' => $days]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
