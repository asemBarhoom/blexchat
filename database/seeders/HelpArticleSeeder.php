<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\PricingFaqItem;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PricingFaqItem::query()->orderBy('sort')->each(function (PricingFaqItem $item): void {
            HelpArticle::updateOrCreate(
                ['title->en' => $item->getTranslation('question', 'en', false)],
                [
                    'category' => 'general',
                    'title' => ['en' => $item->getTranslation('question', 'en', false), 'ar' => $item->getTranslation('question', 'ar', false)],
                    'body' => ['en' => $item->getTranslation('answer', 'en', false), 'ar' => $item->getTranslation('answer', 'ar', false)],
                    'sort' => $item->sort,
                    'active' => $item->active,
                ],
            );
        });
    }
}
