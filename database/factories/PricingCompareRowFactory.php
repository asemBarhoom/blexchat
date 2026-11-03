<?php

namespace Database\Factories;

use App\Models\PricingCompareGroup;
use App\Models\PricingCompareRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingCompareRow>
 */
class PricingCompareRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pricing_compare_group_id' => PricingCompareGroup::factory(),
            'key' => fake()->unique()->slug(2),
            'label' => ['en' => ucfirst(fake()->words(2, true))],
        ];
    }

    public function from(string $source): static
    {
        return $this->state(fn (array $attributes) => ['source' => $source]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
