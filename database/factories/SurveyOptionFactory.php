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
            'icon' => null,
            'sort' => 0,
        ];
    }

    /**
     * Show a simple-icons logo, optionally cropped for a wordmark.
     *
     * @param  array{viewBox: string, width: int, height: int}|null  $box
     */
    public function withIcon(string $slug = 'instagram', ?array $box = null): static
    {
        return $this->state(fn (): array => ['icon' => $slug, 'icon_box' => $box]);
    }

    /**
     * Show an uploaded logo from the public disk.
     */
    public function withUploadedIcon(string $path = 'survey/icons/logo.png'): static
    {
        return $this->state(fn (): array => ['icon_image' => $path]);
    }
}
