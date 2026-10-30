<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'platform'        => fake()->randomElement(['instagram', 'whatsapp', 'tiktok', 'messenger']),
            'account_id'      => fake()->numerify('1784140########'),
            'username'        => '@'.fake()->userName(),
            'profile_picture' => fake()->imageUrl(100, 100),
            'access_token'    => fake()->sha256(),
            'followers'       => fake()->numberBetween(100, 100000),
            'status'          => 'active',
            'connected_at'    => now(),
        ];
    }
}
