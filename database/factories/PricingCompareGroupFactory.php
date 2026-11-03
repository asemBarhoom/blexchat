<?php

namespace Database\Factories;

use App\Models\PricingCompareGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingCompareGroup>
 */
class PricingCompareGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => ['en' => ucfirst(fake()->word())],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
