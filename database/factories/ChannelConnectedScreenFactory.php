<?php

namespace Database\Factories;

use App\Models\ChannelConnectedScreen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelConnectedScreen>
 */
class ChannelConnectedScreenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->word());

        return [
            'platform' => fake()->unique()->randomElement(ChannelConnectedScreen::PLATFORMS),
            'channel_name' => ['en' => $name],
            'title' => ['en' => "Your {$name} is connected!"],
            'lead' => ['en' => fake()->sentence()],
            'account_kind' => ['en' => "{$name} · Business account"],
            'status_label' => ['en' => 'Connected'],
            'continue_label' => ['en' => 'Continue'],
            'connect_another_label' => ['en' => 'Connect another channel'],
            'active' => true,
        ];
    }

    public function platform(string $platform): static
    {
        return $this->state(fn (array $attributes) => ['platform' => $platform]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
