<?php

namespace Database\Factories;

use App\Models\FirstMove;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirstMove>
 */
class FirstMoveFactory extends Factory
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
            'trigger' => fake()->randomElement(FirstMove::TRIGGERS),
            'template' => null,
            'title' => ['en' => fake()->sentence(4)],
            'line' => ['en' => fake()->sentence()],
            'incoming_who' => fake()->userName(),
            'incoming_text' => ['en' => fake()->sentence(4)],
            'incoming_kind' => ['en' => 'Comment'],
            'reply' => ['en' => fake()->sentence()],
        ];
    }

    public function withTemplate(string $template = 'price-link'): static
    {
        return $this->state(fn (array $attributes) => ['template' => $template]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
