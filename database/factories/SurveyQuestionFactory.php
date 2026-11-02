<?php

namespace Database\Factories;

use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyQuestion>
 */
class SurveyQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word(),
            'question' => ['en' => fake()->sentence().'?'],
            'hint' => ['en' => 'Pick all that apply.'],
            'kind' => 'multi',
        ];
    }

    public function radio(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => 'radio', 'hint' => ['en' => 'Pick one.']]);
    }

    /**
     * Adds options with these keys, in order.
     *
     * @param  list<string>  $keys
     */
    public function withOptions(array $keys = ['a', 'b', 'c']): static
    {
        return $this->afterCreating(function (SurveyQuestion $question) use ($keys): void {
            foreach ($keys as $sort => $key) {
                $question->options()->create(['key' => $key, 'label' => ['en' => ucfirst($key)], 'sort' => $sort]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
