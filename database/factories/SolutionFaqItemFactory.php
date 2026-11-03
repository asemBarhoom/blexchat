<?php

namespace Database\Factories;

use App\Models\Solution;
use App\Models\SolutionFaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolutionFaqItem>
 */
class SolutionFaqItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solution_id' => Solution::factory(),
            'question' => ['en' => rtrim(fake()->sentence(), '.').'?'],
            'answer' => ['en' => fake()->sentence(10)],
            'sort' => 0,
        ];
    }
}
