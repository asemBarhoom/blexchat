<?php

namespace Database\Factories;

use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyOption>
 */
class SurveyOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_question_id' => SurveyQuestion::factory(),
            'key' => fake()->unique()->word(),
            'label' => ['en' => fake()->words(2, true)],
            'sort' => 0,
        ];
    }
}
