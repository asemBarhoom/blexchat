<?php

namespace Database\Factories;

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpArticle>
 */
class HelpArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => 'general',
            'title' => ['en' => fake()->sentence(4), 'ar' => fake()->sentence(4)],
            'body' => ['en' => fake()->paragraph(), 'ar' => fake()->paragraph()],
            'sort' => fake()->numberBetween(0, 20),
            'active' => true,
        ];
    }
}
