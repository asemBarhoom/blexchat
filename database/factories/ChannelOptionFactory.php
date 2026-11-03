<?php

namespace Database\Factories;

use App\Models\ChannelOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelOption>
 */
class ChannelOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = fake()->unique()->randomElement(ChannelOption::PLATFORMS);

        return [
            'platform' => $platform,
            'name' => ['en' => ucfirst($platform)],
            'line' => ['en' => fake()->sentence(4)],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
