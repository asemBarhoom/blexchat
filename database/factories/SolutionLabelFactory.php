<?php

namespace Database\Factories;

use App\Models\SolutionLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolutionLabel>
 */
class SolutionLabelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'texts' => ['en' => ['steps' => ['title' => 'How it works']]],
        ];
    }
}
