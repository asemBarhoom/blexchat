<?php

namespace Database\Factories;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'anthropic',
            'model' => 'test-model',
            'status' => AiUsage::STATUS_COMPLETED,
            'input_tokens' => fake()->numberBetween(50, 500),
            'output_tokens' => fake()->numberBetween(10, 100),
            'estimated_cost_micros' => fake()->numberBetween(100, 5000),
        ];
    }
}
