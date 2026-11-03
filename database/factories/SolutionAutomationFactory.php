<?php

namespace Database\Factories;

use App\Models\Solution;
use App\Models\SolutionAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A DM automation with no post by default; onComment() puts it under a typographic tile.
 *
 * @extends Factory<SolutionAutomation>
 */
class SolutionAutomationFactory extends Factory
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
            'title' => ['en' => fake()->words(3, true)],
            'line' => ['en' => fake()->sentence()],
            'trigger_source' => 'dm',
            'trigger_words' => [],
            'customer' => 'السلام عليكم، كم السعر؟',
            'reply' => 'هلا والله! 👋 التفاصيل كلها هنا:',
            'button' => 'شوف التفاصيل',
            'link' => fake()->domainName().'/'.fake()->slug(1),
            'sort' => 0,
        ];
    }

    public function onComment(string $word = 'السعر'): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_source' => 'comment',
            'trigger_words' => [$word],
            'customer' => $word.' 😍',
            'post_tile_text' => $word,
            'post_tile_tone' => 'pink',
            'post_likes' => '٢٬٤١٨',
        ]);
    }
}
