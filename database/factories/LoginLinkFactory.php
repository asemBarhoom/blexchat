<?php

namespace Database\Factories;

use App\Models\LoginLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginLink>
 */
class LoginLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'mode' => 'signup',
            'expires_at' => now()->addMinutes(LoginLink::LIFETIME_MINUTES),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['used_at' => now()->subMinute()]);
    }
}
