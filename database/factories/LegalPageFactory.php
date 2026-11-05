<?php

namespace Database\Factories;

use App\Models\LegalPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalPage>
 */
class LegalPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'privacy',
            'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
            'body' => ['en' => "## What we collect\n\nYour email address.", 'ar' => "## ما الذي نجمعه\n\nبريدك الإلكتروني."],
            'effective_on' => '2026-11-01',
        ];
    }

    public function terms(): static
    {
        return $this->state([
            'slug' => 'terms',
            'title' => ['en' => 'Terms of Service', 'ar' => 'شروط الخدمة'],
            'body' => ['en' => "## Your account\n\nKeep it safe.", 'ar' => "## حسابك\n\nحافظ عليه."],
        ]);
    }
}
