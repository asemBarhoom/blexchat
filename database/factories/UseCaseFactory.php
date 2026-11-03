<?php

namespace Database\Factories;

use App\Models\UseCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UseCase>
 */
class UseCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'key' => $slug,
            'slug' => $slug,
            'name' => ['en' => ucfirst(fake()->word())],
            'line' => ['en' => fake()->sentence()],
            'shape' => fake()->randomElement(UseCase::SHAPES),
            'tone' => fake()->randomElement(UseCase::TONES),
        ];
    }
}
