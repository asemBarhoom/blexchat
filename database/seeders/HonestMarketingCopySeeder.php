<?php

namespace Database\Seeders;

use App\Models\Creator;
use App\Models\HomeBlock;
use App\Models\HomeStat;
use Illuminate\Database\Seeder;

/**
 * Takes the claims the business cannot back up off the marketing homepage, on an existing
 * database: the partner badges, the rating and customer count, the invented creators and the
 * "proof" numbers. Safe to run twice — it only updates these records, matched by key or by the
 * English label the homepage seeded.
 */
class HonestMarketingCopySeeder extends Seeder
{
    /** The invented creator testimonials the homepage seeded, by key. */
    private const CREATOR_KEYS = ['nora', 'omar', 'lina', 'faisal', 'huda'];

    public function run(): void
    {
        $this->honestHero();
        $this->honestCreators();
        $this->honestStats();
    }

    /**
     * The hero keeps a line that is true: what the product works with. No rating, no customer
     * count, no customer faces and no partner badges.
     */
    private function honestHero(): void
    {
        $block = HomeBlock::where('key', 'hero')->first();

        if ($block === null) {
            return;
        }

        $texts = $block->getTranslations('texts');
        $texts['en']['social_proof'] = ['text' => 'Built for Instagram, WhatsApp, TikTok & Messenger'];
        $texts['ar']['social_proof'] = ['text' => 'مصمَّم لـ Instagram وWhatsApp وTikTok وMessenger'];
        unset($texts['en']['partners'], $texts['ar']['partners']);

        $settings = $block->settings ?? [];
        unset($settings['social_proof'], $settings['partners']);

        $block->setTranslations('texts', $texts);
        $block->settings = $settings;
        $block->save();
    }

    /**
     * The creators strip stops claiming a million fans, and its invented people are hidden.
     */
    private function honestCreators(): void
    {
        $block = HomeBlock::where('key', 'creators')->first();

        if ($block !== null) {
            $texts = $block->getTranslations('texts');
            $texts['en']['label'] = 'Built for creators, brands and sellers';
            $texts['ar']['label'] = 'مصمَّم لصنّاع المحتوى والعلامات التجارية والبائعين';
            $block->setTranslations('texts', $texts);
            $block->save();
        }

        Creator::whereIn('key', self::CREATOR_KEYS)->update(['active' => false]);
    }

    /**
     * The four seeded proof numbers become product facts. Matched by the English label they
     * were seeded with, so a second run (or a database already seeded honestly) changes nothing.
     */
    private function honestStats(): void
    {
        $replacements = [
            'Messages automated' => ['4', '4', 'channels in one inbox', 'قنوات في صندوق وارد واحد'],
            'Average reply time' => ['24/7', '24/7', 'automatic replies', 'ردود تلقائية'],
            'Revenue recovered' => ['AR · EN', 'عربي · إنجليزي', 'Arabic & English', 'العربية والإنجليزية'],
            'Businesses' => ['Seconds', 'ثوانٍ', 'for every reply', 'لكل رد'],
        ];

        HomeStat::all()->each(function (HomeStat $stat) use ($replacements): void {
            $replacement = $replacements[$stat->getTranslation('label', 'en')] ?? null;

            if ($replacement === null) {
                return;
            }

            [$valueEn, $valueAr, $labelEn, $labelAr] = $replacement;

            $stat->setTranslations('value', ['en' => $valueEn, 'ar' => $valueAr]);
            $stat->setTranslations('label', ['en' => $labelEn, 'ar' => $labelAr]);
            $stat->save();
        });
    }
}
