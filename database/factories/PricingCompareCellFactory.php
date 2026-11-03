<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PricingCompareCell;
use App\Models\PricingCompareRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingCompareCell>
 */
class PricingCompareCellFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pricing_compare_row_id' => PricingCompareRow::factory(),
            'plan_id' => Plan::factory(),
            'included' => fake()->boolean(),
        ];
    }

    /**
     * @param  array<string, string>  $text  per locale
     */
    public function text(array $text): static
    {
        return $this->state(fn (array $attributes) => ['text' => $text]);
    }
}
