<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'channel_id'   => Channel::factory(),
            'name'         => fake()->sentence(3),
            'trigger_type' => fake()->randomElement(['comment_keyword', 'dm_keyword', 'story_mention']),
            'keywords'     => fake()->words(3),
            'actions'      => [
                ['type' => 'reply_comment', 'message' => fake()->sentence()],
            ],
            'active'       => true,
        ];
    }
}
