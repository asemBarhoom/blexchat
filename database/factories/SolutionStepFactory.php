<?php

namespace Database\Factories;

use App\Models\Solution;
use App\Models\SolutionStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolutionStep>
 */
class SolutionStepFactory extends Factory
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
            'text' => ['en' => fake()->sentence()],
            'sort' => 0,
        ];
    }
}
