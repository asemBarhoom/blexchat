<?php

namespace Database\Seeders;

use App\Models\FirstMove;
use Illuminate\Database\Seeder;

/**
 * The "popular first move" cards of onboarding W10, as the frontend shows them
 * (src/pages/auth/FirstMove.tsx). Sample names and messages are placeholders; admins edit them
 * in the panel. Safe to run again: rows are matched by key.
 */
class FirstMoveSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->moves() as $sort => $move) {
            FirstMove::updateOrCreate(['key' => $move['key']], [...$move, 'sort' => $sort]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moves(): array
    {
        return [
            [
                'key' => 'comment-dm',
                'trigger' => 'comment',
                'template' => null,
                'title' => ['en' => 'Auto-DM links from comments', 'ar' => 'أرسل الروابط تلقائياً من التعليقات'],
                'line' => [
                    'en' => 'Someone comments a keyword on your post — they get your link in a DM, instantly.',
                    'ar' => 'يعلّق أحدهم بكلمة على منشورك — فيصله رابطك في رسالة خاصة فوراً.',
                ],
                'incoming_who' => 'reem.k',
                'incoming_text' => ['en' => 'LINK 😍 how much?', 'ar' => 'الرابط 😍 بكم؟'],
                'incoming_kind' => ['en' => 'Comment', 'ar' => 'تعليق'],
                'reply' => ['en' => 'Here’s the link 👉 nourboutique.com/abaya', 'ar' => 'تفضلي الرابط 👉 nourboutique.com/abaya'],
            ],
            [
                'key' => 'dm-replies',
                'trigger' => 'dm',
                'template' => null,
                'title' => ['en' => 'Respond to all your DMs', 'ar' => 'ردّ على كل رسائلك الخاصة'],
                'line' => [
                    'en' => 'Greet every new message and answer the common questions, day and night.',
                    'ar' => 'رحّب بكل رسالة جديدة وأجب عن الأسئلة المتكررة، ليلاً ونهاراً.',
                ],
                'incoming_who' => 'faisal.a',
                'incoming_text' => ['en' => 'Do you deliver to Riyadh?', 'ar' => 'توصلون للرياض؟'],
                'incoming_kind' => ['en' => 'DM', 'ar' => 'رسالة خاصة'],
                'reply' => ['en' => 'Yes! Riyadh in 1–2 days, free over 200 SAR ✓', 'ar' => 'أكيد! الرياض خلال 1–2 يوم، والتوصيل مجاني فوق 200 ريال ✓'],
            ],
        ];
    }
}
