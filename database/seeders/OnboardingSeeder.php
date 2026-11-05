<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\SurveyQuestion;
use Illuminate\Database\Seeder;

/**
 * The plan ladder and the onboarding survey, as the frontend shows them (src/app/plans.ts and
 * src/pages/auth/surveyContent.ts). Prices, limits and option lists are placeholders until the
 * client confirms them; admins edit them in the panel. Safe to run again: rows are matched by slug and key.
 */
class OnboardingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->plans() as $sort => $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], [...$plan, 'sort' => $sort]);
        }

        foreach ($this->survey() as $sort => $question) {
            $stored = SurveyQuestion::updateOrCreate(['key' => $question['key']], [...collect($question)->except('options')->all(), 'sort' => $sort]);

            foreach ($question['options'] as $optionSort => $option) {
                $stored->options()->updateOrCreate(['key' => $option['key']], [...$option, 'sort' => $optionSort]);
            }
        }

        $this->removeRetiredOptions();
    }

    /**
     * Options the frontend has dropped since they were first seeded: Salla gave way to Wix.
     */
    private function removeRetiredOptions(): void
    {
        SurveyQuestion::where('key', 'tools')->first()?->options()->where('key', 'salla')->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'slug' => 'free',
                'name' => ['en' => 'Free', 'ar' => 'Free'],
                'tagline' => ['en' => 'Try it on one channel.', 'ar' => 'جرّبه على قناة واحدة.'],
                'monthly_price' => 0,
                'annual_price' => 0,
                'contacts_limit' => 25,
                'has_ai' => false,
                'has_email_capture' => false,
                'features' => [
                    'en' => ['1 channel', 'Comment and DM replies', 'Blexchat branding'],
                    'ar' => ['قناة واحدة', 'الرد على التعليقات والرسائل الخاصة', 'تظهر علامة Blexchat'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
            [
                'slug' => 'essential',
                'name' => ['en' => 'Essential', 'ar' => 'Essential'],
                'tagline' => ['en' => 'For a growing shop or creator.', 'ar' => 'للمتاجر وصنّاع المحتوى في مرحلة النمو.'],
                'monthly_price' => 17,
                'annual_price' => 14,
                'contacts_limit' => 250,
                'has_ai' => false,
                'has_email_capture' => false,
                'features' => [
                    'en' => ['All four channels', 'Unlimited automations', 'No Blexchat branding'],
                    'ar' => ['القنوات الأربع كلها', 'أتمتات غير محدودة', 'بدون شعار Blexchat'],
                ],
                'trial_days' => 14,
                'is_recommended' => true,
            ],
            [
                'slug' => 'pro',
                'name' => ['en' => 'Pro', 'ar' => 'Pro'],
                'tagline' => ['en' => 'When DMs become a sales channel.', 'ar' => 'حين تصبح الرسائل الخاصة قناة مبيعات.'],
                'monthly_price' => 39,
                'annual_price' => 29,
                'contacts_limit' => 2500,
                'has_ai' => true,
                'has_email_capture' => true,
                'features' => [
                    'en' => ['Everything in Essential', 'Follow-ups and email capture', 'Inbox for 3 teammates'],
                    'ar' => ['كل مزايا Essential', 'رسائل المتابعة وجمع البريد الإلكتروني', 'صندوق الوارد لـ 3 من أعضاء الفريق'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
            [
                'slug' => 'business',
                'name' => ['en' => 'Business', 'ar' => 'Business'],
                'tagline' => ['en' => 'For teams answering at volume.', 'ar' => 'للفرق التي ترد على أعداد كبيرة من الرسائل.'],
                'monthly_price' => 99,
                'annual_price' => 69,
                'contacts_limit' => 7500,
                'has_ai' => true,
                'has_email_capture' => true,
                'features' => [
                    'en' => ['Everything in Pro', 'Team roles and assignment', 'Priority support'],
                    'ar' => ['كل مزايا Pro', 'أدوار الفريق وتوزيع المحادثات', 'دعم ذو أولوية'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
            [
                'slug' => 'advanced',
                'name' => ['en' => 'Advanced', 'ar' => 'Advanced'],
                'tagline' => ['en' => 'For brands and agencies at scale.', 'ar' => 'للعلامات التجارية والوكالات الكبيرة.'],
                'monthly_price' => 199,
                'annual_price' => 139,
                'contacts_limit' => 25000,
                'has_ai' => true,
                'has_email_capture' => true,
                'features' => [
                    'en' => ['Everything in Business', 'Many accounts, one seat', 'Onboarding call'],
                    'ar' => ['كل مزايا Business', 'حسابات كثيرة بمستخدم واحد', 'مكالمة الإعداد الأولي'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function survey(): array
    {
        return [
            [
                'key' => 'for',
                'question' => ['en' => 'Who is this account for?', 'ar' => 'لمن هذا الحساب؟'],
                'hint' => ['en' => 'Pick one.', 'ar' => 'اختر خيارًا واحدًا.'],
                'kind' => 'radio',
                'other_option' => null,
                'options' => [
                    ['key' => 'employer', 'label' => ['en' => 'The company I work for', 'ar' => 'الشركة التي أعمل بها'], 'note' => ['en' => 'You run its social channels.', 'ar' => 'تدير قنواتها على مواقع التواصل.']],
                    ['key' => 'myself', 'label' => ['en' => 'Myself', 'ar' => 'لنفسي'], 'note' => ['en' => 'My own brand, shop or creator account.', 'ar' => 'علامتي التجارية أو متجري أو حسابي كصانع محتوى.']],
                    ['key' => 'client', 'label' => ['en' => 'A client', 'ar' => 'عميل'], 'note' => ['en' => 'You manage accounts for others.', 'ar' => 'تدير حسابات لآخرين.']],
                ],
            ],
            [
                'key' => 'money',
                'question' => ['en' => 'How do you make money?', 'ar' => 'كيف تحقق دخلك؟'],
                'hint' => ['en' => 'Pick all that apply.', 'ar' => 'اختر كل ما ينطبق.'],
                'kind' => 'multi',
                'other_option' => null,
                'options' => [
                    ['key' => 'products', 'label' => ['en' => 'Selling products', 'ar' => 'بيع المنتجات']],
                    ['key' => 'services', 'label' => ['en' => 'Selling services or bookings', 'ar' => 'بيع الخدمات أو الحجوزات']],
                    ['key' => 'deals', 'label' => ['en' => 'Brand deals and sponsorships', 'ar' => 'صفقات مع العلامات التجارية ورعايات']],
                    ['key' => 'courses', 'label' => ['en' => 'Courses, coaching or memberships', 'ar' => 'دورات أو تدريب أو اشتراكات عضوية']],
                    ['key' => 'affiliate', 'label' => ['en' => 'Affiliate links', 'ar' => 'روابط التسويق بالعمولة']],
                    ['key' => 'notyet', 'label' => ['en' => 'Not making money yet', 'ar' => 'لا أحقق دخلًا بعد']],
                ],
            ],
            [
                'key' => 'tools',
                'question' => ['en' => 'Which tools do you use?', 'ar' => 'ما الأدوات التي تستخدمها؟'],
                'hint' => ['en' => 'Pick all that apply — we’ll suggest the right connections.', 'ar' => 'اختر كل ما ينطبق — وسنقترح عليك عمليات الربط المناسبة.'],
                'kind' => 'grid',
                'other_option' => null,
                'options' => [
                    ['key' => 'shopify', 'label' => ['en' => 'Shopify'], 'icon' => 'shopify'],
                    ['key' => 'wix', 'label' => ['en' => 'Wix'], 'icon' => 'wix'],
                    ['key' => 'woocommerce', 'label' => ['en' => 'WooCommerce'], 'icon' => 'woocommerce', 'icon_box' => ['viewBox' => '0 9.58 24 4.84', 'width' => 64, 'height' => 13]],
                    ['key' => 'sheets', 'label' => ['en' => 'Google Sheets'], 'icon' => 'googlesheets'],
                    ['key' => 'mailchimp', 'label' => ['en' => 'Mailchimp'], 'icon' => 'mailchimp'],
                    ['key' => 'calendly', 'label' => ['en' => 'Calendly'], 'icon' => 'calendly'],
                    ['key' => 'stripe', 'label' => ['en' => 'Stripe'], 'icon' => 'stripe'],
                    ['key' => 'zapier', 'label' => ['en' => 'Zapier'], 'icon' => 'zapier'],
                ],
            ],
            [
                'key' => 'heard',
                'question' => ['en' => 'How did you hear about us?', 'ar' => 'كيف عرفت عنّا؟'],
                'hint' => ['en' => 'Pick all that apply.', 'ar' => 'اختر كل ما ينطبق.'],
                'kind' => 'multi',
                'other_option' => 'other',
                'options' => [
                    ['key' => 'instagram', 'label' => ['en' => 'Instagram'], 'icon' => 'instagram'],
                    ['key' => 'tiktok', 'label' => ['en' => 'TikTok'], 'icon' => 'tiktok'],
                    ['key' => 'snapchat', 'label' => ['en' => 'Snapchat'], 'icon' => 'snapchat'],
                    ['key' => 'youtube', 'label' => ['en' => 'YouTube'], 'icon' => 'youtube'],
                    ['key' => 'search', 'label' => ['en' => 'Google search', 'ar' => 'البحث في Google']],
                    ['key' => 'friend', 'label' => ['en' => 'A friend or colleague', 'ar' => 'صديق أو زميل']],
                    ['key' => 'other', 'label' => ['en' => 'Other', 'ar' => 'غير ذلك']],
                ],
            ],
        ];
    }
}
