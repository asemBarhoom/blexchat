<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id'               => Channel::factory(),
            'platform_conversation_id' => fake()->numerify('conv_############'),
            'contact_name'             => fake()->userName(),
            'contact_avatar'           => fake()->imageUrl(50, 50),
            'contact_platform_id'      => fake()->numerify('user_#########'),
            'status'                   => 'open',
            'unread_count'             => fake()->numberBetween(0, 5),
            'last_message'             => fake()->sentence(),
            'last_message_at'          => fake()->dateTimeThisMonth(),
        ];
    }
}
