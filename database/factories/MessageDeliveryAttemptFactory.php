<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\MessageDeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageDeliveryAttempt>
 */
class MessageDeliveryAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'idempotency_key' => fake()->unique()->uuid(),
            'content' => fake()->sentence(),
            'type' => 'text',
            'status' => 'failed',
            'error_code' => 'provider_error',
            'error_message' => fake()->sentence(),
            'attempts' => 1,
            'last_attempt_at' => now(),
        ];
    }

    /**
     * An attempt that got through on one of its tries.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'sent',
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    /**
     * A platform that cannot send replies yet: retrying it cannot succeed.
     */
    public function channelNotLive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'error_code' => 'channel_not_live',
            'error_message' => 'Replies on tiktok are not available yet.',
        ]);
    }
}
