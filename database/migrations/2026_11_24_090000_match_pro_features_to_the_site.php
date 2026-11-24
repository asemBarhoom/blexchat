<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pro lists four lines, as the site has a translation for each: unlimited automations and
     * no branding share one line. A fifth line showed as a raw key on the pricing page.
     */
    public function up(): void
    {
        $this->setProFeatures([
            'en' => ['All four channels', 'Unlimited automations, no Blexchat branding', 'Follow-ups and email capture', 'Inbox for 3 teammates'],
            'ar' => ['القنوات الأربع كلها', 'أتمتات غير محدودة وبدون شعار Blexchat', 'رسائل المتابعة وجمع البريد الإلكتروني', 'صندوق الوارد لـ 3 من أعضاء الفريق'],
        ]);
    }

    public function down(): void
    {
        $this->setProFeatures([
            'en' => ['All four channels', 'Unlimited automations', 'No Blexchat branding', 'Follow-ups and email capture', 'Inbox for 3 teammates'],
            'ar' => ['القنوات الأربع كلها', 'أتمتات غير محدودة', 'بدون شعار Blexchat', 'رسائل المتابعة وجمع البريد الإلكتروني', 'صندوق الوارد لـ 3 من أعضاء الفريق'],
        ]);
    }

    /**
     * @param  array{en: list<string>, ar: list<string>}  $features
     */
    private function setProFeatures(array $features): void
    {
        DB::table('plans')->where('slug', 'pro')->update(['features' => json_encode($features, JSON_UNESCAPED_UNICODE)]);
    }
};
