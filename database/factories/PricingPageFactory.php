<?php

namespace Database\Factories;

use App\Models\PricingPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingPage>
 */
class PricingPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'texts' => ['en' => [
                'hero' => ['chip' => 'Pricing', 'title' => fake()->sentence(), 'save' => 'Save up to {max_saving}%'],
                'compare' => ['title' => 'Compare the plans'],
            ]],
            'agency_url' => '/solutions/agencies',
        ];
    }
}
