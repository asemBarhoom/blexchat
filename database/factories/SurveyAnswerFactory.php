<?php

namespace Database\Factories;

use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyAnswer>
 */
class SurveyAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'survey_question_id' => SurveyQuestion::factory()->withOptions(['a', 'b']),
            'options' => ['a'],
        ];
    }
}
