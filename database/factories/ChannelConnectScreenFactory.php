<?php

namespace Database\Factories;

use App\Models\ChannelConnectScreen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelConnectScreen>
 */
class ChannelConnectScreenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'platform' => strtolower($name),
            'method' => 'meta',
            'name' => ['en' => $name],
            'cta' => ['en' => 'Connect via Meta'],
            'lead' => ['en' => fake()->sentence()],
            'steps' => ['en' => [fake()->sentence(3), fake()->sentence(3), fake()->sentence(3)]],
            'options' => ['en' => [['title' => fake()->sentence(3), 'note' => fake()->sentence(), 'url' => null]]],
            'account_kind' => ['en' => "{$name} · Business account"],
            'can' => ['en' => 'answer messages'],
            'reconnect_cta' => ['en' => 'Reconnect via Meta'],
            'waiting_in' => ['en' => 'the Meta window'],
            'ineligible_title' => ['en' => 'This is a personal account.'],
            'ineligible_text' => ['en' => fake()->sentence()],
            'ineligible_kind' => ['en' => "{$name} · Personal account"],
            'ineligible_how' => ['en' => 'How to switch'],
            'ineligible_help_url' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
