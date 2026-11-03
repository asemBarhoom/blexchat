<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('????##'),
            'percent_off' => fake()->randomElement([10, 15, 20, 25]),
            'active' => true,
            'expires_at' => null,
            'usage_limit' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    public function usedUp(): static
    {
        return $this->state(fn (array $attributes) => ['usage_limit' => 5])
            ->afterCreating(fn (Coupon $coupon) => $coupon->forceFill(['times_used' => 5])->save());
    }
}
