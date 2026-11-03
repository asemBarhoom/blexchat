<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelAccessRequest>
 */
class ChannelAccessRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel_id' => Channel::factory(),
            'platform' => fn (array $attributes): string => Channel::find($attributes['channel_id'])?->platform ?? 'instagram',
            'account_id' => fn (array $attributes): string => Channel::find($attributes['channel_id'])?->account_id ?? fake()->numerify('1784140########'),
            'account_handle' => fn (array $attributes): ?string => Channel::find($attributes['channel_id'])?->username,
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved']);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'declined']);
    }
}
