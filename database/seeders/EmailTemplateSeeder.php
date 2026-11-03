<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Adds every email template that is missing, with its lang/ texts in each language. Templates
 * already there keep their admin-edited texts, so this is safe to run again.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EmailTemplate::TEMPLATES as $key => $langKeys) {
            EmailTemplate::firstOrCreate(['key' => $key], collect($langKeys)->map(
                fn (string $langKey): array => collect(config('app.supported_locales'))
                    ->mapWithKeys(fn (string $locale): array => [$locale => EmailTemplate::defaultText($langKey, $locale)])
                    ->all(),
            )->all());
        }
    }
}
