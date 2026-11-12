<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['instagram', 'whatsapp', 'messenger', 'stripe']),
            'event_key' => fake()->unique()->uuid(),
            'status' => 'received',
            'object' => null,
            'payload' => ['event' => 'message'],
            'error' => null,
        ];
    }
}
