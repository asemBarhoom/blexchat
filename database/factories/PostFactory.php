<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'platform_post_id' => fake()->unique()->numerify('1790##########'),
            'caption' => fake()->sentence(),
            'thumbnail_url' => fake()->imageUrl(),
            'media_type' => fake()->randomElement(['image', 'video', 'carousel', 'reel']),
            'posted_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
