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
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'slug' => 'free',
                'name' => ['en' => 'Free', 'ar' => 'مجانية'],
                'tagline' => ['en' => 'Try it on one channel.', 'ar' => 'جرّبها على قناة واحدة.'],
                'monthly_price' => 0,
                'annual_price' => 0,
                'contacts_limit' => 25,
                'has_ai' => false,
                'features' => [
                    'en' => ['1 channel', 'Comment and DM replies', 'Blexchat branding'],
                    'ar' => ['قناة واحدة', 'ردود على التعليقات والرسائل', 'شعار Blexchat'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
            [
                'slug' => 'essential',
                'name' => ['en' => 'Essential', 'ar' => 'الأساسية'],
                'tagline' => ['en' => 'For a growing shop or creator.', 'ar' => 'لمتجر أو صانع محتوى في نمو.'],
                'monthly_price' => 17,
                'annual_price' => 14,
                'contacts_limit' => 250,
                'has_ai' => false,
                'features' => [
                    'en' => ['All four channels', 'Unlimited automations', 'No Blexchat branding'],
                    'ar' => ['القنوات الأربع كلها', 'أتمتة بلا حدود', 'بدون شعار Blexchat'],
                ],
                'trial_days' => 14,
                'is_recommended' => true,
            ],
            [
                'slug' => 'pro',
                'name' => ['en' => 'Pro', 'ar' => 'الاحترافية'],
                'tagline' => ['en' => 'When DMs become a sales channel.', 'ar' => 'حين تصبح الرسائل قناة مبيعات.'],
                'monthly_price' => 39,
                'annual_price' => 29,
                'contacts_limit' => 2500,
                'has_ai' => true,
                'features' => [
                    'en' => ['Everything in Essential', 'Follow-ups and email capture', 'Inbox for 3 teammates'],
                    'ar' => ['كل ما في الأساسية', 'رسائل متابعة وجمع الإيميلات', 'صندوق رسائل لـ 3 من الفريق'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
            [
                'slug' => 'business',
                'name' => ['en' => 'Business', 'ar' => 'الأعمال'],
                'tagline' => ['en' => 'For teams answering at volume.', 'ar' => 'لفرق ترد على عدد كبير من الرسائل.'],
                'monthly_price' => 99,
                'annual_price' => 69,
                'contacts_limit' => 7500,
                'has_ai' => true,
                'features' => [
                    'en' => ['Everything in Pro', 'Team roles and assignment', 'Priority support'],
                    'ar' => ['كل ما في الاحترافية', 'أدوار الفريق وتوزيع المحادثات', 'دعم بأولوية'],
                ],
                'trial_days' => 0,
                'is_recommended' => false,
            ],
            [
                'slug' => 'advanced',
                'name' => ['en' => 'Advanced', 'ar' => 'المتقدمة'],
                'tagline' => ['en' => 'For brands and agencies at scale.', 'ar' => 'للعلامات التجارية والوكالات الكبيرة.'],
                'monthly_price' => 199,
                'annual_price' => 139,
                'contacts_limit' => 25000,
                'has_ai' => true,
                'features' => [
                    'en' => ['Everything in Business', 'Many accounts, one seat', 'Onboarding call'],
                    'ar' => ['كل ما في الأعمال', 'حسابات كثيرة بمقعد واحد', 'مكالمة لتجهيز الحساب'],
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
                'hint' => ['en' => 'Pick one.', 'ar' => 'اختر واحداً.'],
                'kind' => 'radio',
                'other_option' => null,
                'options' => [
                    ['key' => 'employer', 'label' => ['en' => 'The company I work for', 'ar' => 'الشركة التي أعمل فيها'], 'note' => ['en' => 'You run its social channels.', 'ar' => 'تدير حساباتها على مواقع التواصل.']],
                    ['key' => 'myself', 'label' => ['en' => 'Myself', 'ar' => 'لنفسي'], 'note' => ['en' => 'My own brand, shop or creator account.', 'ar' => 'علامتي أو متجري أو حسابي كصانع محتوى.']],
                    ['key' => 'client', 'label' => ['en' => 'A client', 'ar' => 'لعميل'], 'note' => ['en' => 'You manage accounts for others.', 'ar' => 'تدير حسابات لآخرين.']],
                ],
            ],
            [
                'key' => 'money',
                'question' => ['en' => 'How do you make money?', 'ar' => 'كيف تحقق دخلك؟'],
                'hint' => ['en' => 'Pick all that apply.', 'ar' => 'اختر كل ما ينطبق.'],
                'kind' => 'multi',
                'other_option' => null,
                'options' => [
                    ['key' => 'products', 'label' => ['en' => 'Selling products', 'ar' => 'بيع منتجات']],
                    ['key' => 'services', 'label' => ['en' => 'Selling services or bookings', 'ar' => 'بيع خدمات أو حجوزات']],
                    ['key' => 'deals', 'label' => ['en' => 'Brand deals and sponsorships', 'ar' => 'إعلانات ورعايات']],
                    ['key' => 'courses', 'label' => ['en' => 'Courses, coaching or memberships', 'ar' => 'دورات أو تدريب أو اشتراكات']],
                    ['key' => 'affiliate', 'label' => ['en' => 'Affiliate links', 'ar' => 'روابط تسويق بالعمولة']],
                    ['key' => 'notyet', 'label' => ['en' => 'Not making money yet', 'ar' => 'لا أحقق دخلاً بعد']],
                ],
            ],
            [
                'key' => 'tools',
                'question' => ['en' => 'Which tools do you use?', 'ar' => 'ما الأدوات التي تستخدمها؟'],
                'hint' => ['en' => 'Pick all that apply — we’ll suggest the right connections.', 'ar' => 'اختر كل ما ينطبق، وسنقترح الربط المناسب.'],
                'kind' => 'grid',
                'other_option' => null,
                'options' => [
                    ['key' => 'shopify', 'label' => ['en' => 'Shopify']],
                    ['key' => 'salla', 'label' => ['en' => 'Salla', 'ar' => 'سلة']],
                    ['key' => 'woocommerce', 'label' => ['en' => 'WooCommerce']],
                    ['key' => 'sheets', 'label' => ['en' => 'Google Sheets']],
                    ['key' => 'mailchimp', 'label' => ['en' => 'Mailchimp']],
                    ['key' => 'calendly', 'label' => ['en' => 'Calendly']],
                    ['key' => 'stripe', 'label' => ['en' => 'Stripe']],
                    ['key' => 'zapier', 'label' => ['en' => 'Zapier']],
                ],
            ],
            [
                'key' => 'heard',
                'question' => ['en' => 'How did you hear about us?', 'ar' => 'كيف عرفت عنّا؟'],
                'hint' => ['en' => 'Pick all that apply.', 'ar' => 'اختر كل ما ينطبق.'],
                'kind' => 'multi',
                'other_option' => 'other',
                'options' => [
                    ['key' => 'instagram', 'label' => ['en' => 'Instagram']],
                    ['key' => 'tiktok', 'label' => ['en' => 'TikTok']],
                    ['key' => 'snapchat', 'label' => ['en' => 'Snapchat']],
                    ['key' => 'youtube', 'label' => ['en' => 'YouTube']],
                    ['key' => 'search', 'label' => ['en' => 'Google search', 'ar' => 'بحث Google']],
                    ['key' => 'friend', 'label' => ['en' => 'A friend or colleague', 'ar' => 'صديق أو زميل']],
                    ['key' => 'other', 'label' => ['en' => 'Other', 'ar' => 'غير ذلك']],
                ],
            ],
        ];
    }
}
