<?php

namespace Database\Factories;

use App\Models\Solution;
use App\Models\SolutionAutomation;
use App\Models\SolutionFaqItem;
use App\Models\SolutionStep;
use App\Models\UseCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A standalone page (its own slug, name, shape and tone) by default; forUseCase() links it to a card.
 *
 * @extends Factory<Solution>
 */
class SolutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => ucfirst(fake()->word())],
            'shape' => fake()->randomElement(UseCase::SHAPES),
            'tone' => fake()->randomElement(Solution::TONES),
            'sample_handle' => fake()->unique()->userName(),
            'avatar_color' => '#0f7a5a',
            'avatar_text_color' => '#ffffff',
            'headline' => ['en' => fake()->sentence()],
            'supporting' => ['en' => fake()->sentence(12)],
            'closing' => ['en' => fake()->sentence()],
        ];
    }

    public function forUseCase(?UseCase $useCase = null): static
    {
        return $this->state(fn (array $attributes) => [
            'use_case_id' => $useCase ?? UseCase::factory(),
            'slug' => null,
            'name' => null,
            'shape' => null,
            'tone' => null,
        ]);
    }

    /**
     * Every section filled: three automations, three steps and two questions.
     */
    public function complete(): static
    {
        return $this
            ->has(SolutionAutomation::factory()->count(3)->sequence(fn ($sequence) => ['sort' => $sequence->index]), 'automations')
            ->has(SolutionStep::factory()->count(3)->sequence(fn ($sequence) => ['sort' => $sequence->index]), 'steps')
            ->has(SolutionFaqItem::factory()->count(2)->sequence(fn ($sequence) => ['sort' => $sequence->index]), 'faqItems');
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
