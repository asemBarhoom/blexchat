<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'direction'       => fake()->randomElement(['inbound', 'outbound']),
            'type'            => 'text',
            'content'         => fake()->sentence(),
            'automated'       => false,
            'sent_at'         => fake()->dateTimeThisMonth(),
        ];
    }
}
