<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Gives the plans their Arabic names on an existing database. Only a name still left in
 * English is changed, so a name written in the admin stays. Safe to run twice.
 */
class ArabicPlanNamesSeeder extends Seeder
{
    /** @var array<string, string> */
    public const NAMES = [
        'free' => 'مجانية',
        'essential' => 'أساسية',
        'pro' => 'احترافية',
        'business' => 'أعمال',
        'advanced' => 'متقدمة',
    ];

    public function run(): void
    {
        Plan::whereIn('slug', array_keys(self::NAMES))->get()->each(function (Plan $plan): void {
            $arabic = $plan->getTranslation('name', 'ar', false);

            if ($arabic === '' || $arabic === $plan->getTranslation('name', 'en', false)) {
                $plan->setTranslation('name', 'ar', self::NAMES[$plan->slug])->save();
            }
        });
    }
}
