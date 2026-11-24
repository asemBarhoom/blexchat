<?php

namespace Database\Factories;

use App\Models\ReferralCommission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCommission>
 */
class ReferralCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_id' => User::factory(),
            'referred_user_id' => User::factory(),
            'source' => 'invoice:in_'.Str::random(14),
            'payment_cents' => 2900,
            'rate' => 20,
            'amount_cents' => 580,
            'currency' => 'usd',
            'status' => ReferralCommission::PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ReferralCommission::PAID, 'paid_at' => now()]);
    }
}
