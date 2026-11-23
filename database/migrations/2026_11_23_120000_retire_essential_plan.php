<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Essential is no longer sold: it is hidden everywhere, Pro becomes the recommended plan with the 14-day trial.
     */
    public function up(): void
    {
        DB::table('plans')->where('slug', 'essential')->update(['active' => false, 'in_checkout' => false, 'is_recommended' => false, 'trial_days' => 0]);

        DB::table('pricing_faq_items')->where('question->en', 'Is there a free trial?')->update(['active' => false]);

        DB::table('plans')->where('slug', 'pro')->update([
            'is_recommended' => true,
            'trial_days' => 0,
            'features' => json_encode([
                'en' => ['All four channels', 'Unlimited automations', 'No Blexchat branding', 'Follow-ups and email capture', 'Inbox for 3 teammates'],
                'ar' => ['القنوات الأربع كلها', 'أتمتات غير محدودة', 'بدون شعار Blexchat', 'رسائل المتابعة وجمع البريد الإلكتروني', 'صندوق الوارد لـ 3 من أعضاء الفريق'],
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function down(): void
    {
        DB::table('pricing_faq_items')->where('question->en', 'Is there a free trial?')->update(['active' => true]);

        DB::table('plans')->where('slug', 'essential')->update(['active' => true, 'in_checkout' => true, 'is_recommended' => true, 'trial_days' => 14]);

        DB::table('plans')->where('slug', 'pro')->update(['is_recommended' => false, 'trial_days' => 0]);
    }
};
