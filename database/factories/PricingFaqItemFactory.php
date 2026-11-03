<?php

namespace Database\Factories;

use App\Models\PricingFaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingFaqItem>
 */
class PricingFaqItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => ['en' => rtrim(fake()->sentence(), '.').'?'],
            'answer' => ['en' => fake()->paragraph()],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
