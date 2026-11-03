<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'login_link.signup',
            'subject' => ['en' => fake()->sentence(3)],
            'line' => ['en' => fake()->sentence()],
            'button' => ['en' => fake()->words(2, true)],
            'expiry' => ['en' => 'Valid for :minutes minutes.'],
            'ignore' => ['en' => fake()->sentence()],
        ];
    }
}
