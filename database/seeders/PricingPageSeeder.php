<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PricingCompareGroup;
use App\Models\PricingFaqItem;
use App\Models\PricingPage;
use Illuminate\Database\Seeder;

/**
 * The pricing page as the frontend shows it (src/pages/marketing/PricingPage.tsx and pricingContent.ts):
 * its copy, the plan comparison table and the FAQ. Runs after OnboardingSeeder, whose plans the table
 * points at. Safe to run again: groups and rows are matched by key, cells by plan, questions by their English.
 */
class PricingPageSeeder extends Seeder
{
    public function run(): void
    {
        (PricingPage::current() ?? new PricingPage)->fill(['texts' => $this->copy(), 'agency_url' => '/solutions/agencies'])->save();

        $plans = Plan::query()->pluck('id', 'slug');

        foreach ($this->compare() as $groupSort => $group) {
            $storedGroup = PricingCompareGroup::updateOrCreate(['key' => $group['key']], ['title' => $group['title'], 'sort' => $groupSort]);

            foreach ($group['rows'] as $rowSort => $row) {
                $storedRow = $storedGroup->rows()->updateOrCreate(
                    ['key' => $row['key']],
                    ['label' => $row['label'], 'source' => $row['source'] ?? null, 'sort' => $groupSort * 100 + $rowSort],
                );

                foreach ($row['cells'] ?? [] as $slug => $cell) {
                    if (! $plans->has($slug)) {
                        continue;
                    }

                    $storedRow->cells()->updateOrCreate(['plan_id' => $plans[$slug]], is_bool($cell)
                        ? ['included' => $cell, 'text' => null]
                        : ['included' => false, 'text' => $cell]);
                }
            }
        }

        foreach ($this->faq() as $sort => $item) {
            PricingFaqItem::query()->where('question->en', $item['question']['en'])->firstOrNew()
                ->fill([...$item, 'sort' => $sort])
                ->save();
        }
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function copy(): array
    {
        return [
            'en' => [
                'hero' => [
                    'chip' => 'Pricing',
                    'title' => 'Start free. Pay when your audience grows.',
                    'lead' => 'Every plan answers comments and DMs for you. You pay for the number of people you talk to, nothing else.',
                    'billing_label' => 'Billing period',
                    'monthly' => 'Monthly',
                    'annual' => 'Annually',
                    'save' => 'Save up to {max_saving}%',
                    'note' => 'Prices in US dollars. Change or cancel any time.',
                ],
                'card' => [
                    'ribbon' => 'Most popular',
                    'forever' => 'forever',
                    'per_month' => '/ month',
                    'no_card' => 'No card needed',
                    'billed_annually' => 'Billed ${total} a year',
                    'billed_monthly' => 'Billed monthly',
                    'start_free' => 'Start free',
                    'try_free' => 'Try {days} days free',
                    'choose' => 'Choose {plan}',
                    'contacts' => 'Up to {contacts} contacts',
                    'ai' => '+ Blexchat AI',
                ],
                'compare' => [
                    'title' => 'Compare the plans',
                    'feature' => 'Feature',
                    'included' => 'Included',
                    'not_included' => 'Not included',
                ],
                'agency' => [
                    'title' => 'Running accounts for clients?',
                    'line' => 'Many accounts from one seat, and a plan that fits how agencies bill.',
                    'link_label' => 'Blexchat for agencies',
                ],
                'faq' => [
                    'title' => 'Before you pay.',
                    'marked' => 'pay',
                ],
            ],
            'ar' => [
                'hero' => [
                    'chip' => 'الأسعار',
                    'title' => 'ابدأ مجانًا. وادفع حين يكبر جمهورك.',
                    'lead' => 'كل باقة ترد على التعليقات والرسائل الخاصة نيابةً عنك. تدفع حسب عدد الأشخاص الذين تتحدث معهم، لا أكثر.',
                    'billing_label' => 'دورة الفوترة',
                    'monthly' => 'شهري',
                    'annual' => 'سنوي',
                    'save' => 'وفّر حتى {max_saving}%',
                    'note' => 'الأسعار بالدولار الأمريكي. غيّر باقتك أو ألغِها في أي وقت.',
                ],
                'card' => [
                    'ribbon' => 'الأكثر طلبًا',
                    'forever' => 'للأبد',
                    'per_month' => '/ شهرياً',
                    'no_card' => 'بدون بطاقة دفع',
                    'billed_annually' => 'الفوترة سنويًا: ${total}',
                    'billed_monthly' => 'الفوترة شهريًا',
                    'start_free' => 'ابدأ مجانًا',
                    'try_free' => 'جرّب مجانًا لمدة {days} يوم',
                    'choose' => 'اختر باقة {plan}',
                    'contacts' => 'حتى {contacts} جهة اتصال',
                    'ai' => '+ Blexchat AI',
                ],
                'compare' => [
                    'title' => 'قارن بين الباقات',
                    'feature' => 'الميزة',
                    'included' => 'متاحة',
                    'not_included' => 'غير متاحة',
                ],
                'agency' => [
                    'title' => 'تدير حسابات لعملائك؟',
                    'line' => 'حسابات كثيرة بمستخدم واحد، وباقة تناسب طريقة فوترة الوكالات.',
                    'link_label' => 'Blexchat للوكالات',
                ],
                'faq' => [
                    'title' => 'قبل أن تدفع.',
                    'marked' => 'تدفع',
                ],
            ],
        ];
    }

    /**
     * Each cell, keyed by plan slug, is a tick (true), a dash (false) or text per locale.
     *
     * @return list<array{key: string, title: array<string, string>, rows: list<array<string, mixed>>}>
     */
    private function compare(): array
    {
        $everyPlan = fn (bool|array $cell): array => array_fill_keys(['free', 'essential', 'pro', 'business', 'advanced'], $cell);
        $byPlan = fn (bool|array ...$cells): array => array_combine(['free', 'essential', 'pro', 'business', 'advanced'], $cells);
        $text = fn (string $en, string $ar): array => ['en' => $en, 'ar' => $ar];

        return [
            [
                'key' => 'audience',
                'title' => $text('Audience', 'الجمهور'),
                'rows' => [
                    ['key' => 'contacts', 'label' => $text('Contacts', 'جهات الاتصال'), 'cells' => $everyPlan($text('Up to {contacts}', 'حتى {contacts}'))],
                    ['key' => 'channels', 'label' => $text('Channels', 'القنوات'), 'cells' => [...$everyPlan($text('All four', 'القنوات الأربع')), 'free' => $text('1', '1')]],
                ],
            ],
            [
                'key' => 'automation',
                'title' => $text('Automation', 'الأتمتة'),
                'rows' => [
                    ['key' => 'replies', 'label' => $text('Comment and DM replies', 'الرد على التعليقات والرسائل الخاصة'), 'cells' => $everyPlan(true)],
                    ['key' => 'automations', 'label' => $text('Automations', 'الأتمتات'), 'cells' => [...$everyPlan($text('Unlimited', 'بلا حدود')), 'free' => $text('3', '3')]],
                    ['key' => 'follow-ups', 'label' => $text('Follow-ups and email capture', 'رسائل المتابعة وجمع البريد الإلكتروني'), 'cells' => $byPlan(false, false, true, true, true)],
                    ['key' => 'ai', 'label' => $text('Blexchat AI', 'Blexchat AI'), 'source' => 'has_ai'],
                    ['key' => 'no-branding', 'label' => $text('No Blexchat branding', 'بدون شعار Blexchat'), 'source' => 'paid'],
                ],
            ],
            [
                'key' => 'team',
                'title' => $text('Team', 'الفريق'),
                'rows' => [
                    ['key' => 'teammates', 'label' => $text('Inbox teammates', 'أعضاء الفريق في صندوق الوارد'), 'cells' => $byPlan($text('1', '1'), $text('1', '1'), $text('3', '3'), $text('10', '10'), $text('25', '25'))],
                    ['key' => 'roles', 'label' => $text('Team roles and assignment', 'أدوار الفريق وتوزيع المحادثات'), 'cells' => $byPlan(false, false, false, true, true)],
                    ['key' => 'many-accounts', 'label' => $text('Many accounts, one seat', 'حسابات كثيرة بمستخدم واحد'), 'cells' => $byPlan(false, false, false, false, true)],
                ],
            ],
            [
                'key' => 'support',
                'title' => $text('Support', 'الدعم'),
                'rows' => [
                    ['key' => 'help', 'label' => $text('Help', 'المساعدة'), 'cells' => $byPlan(
                        $text('Help center', 'مركز المساعدة'),
                        $text('Email', 'البريد الإلكتروني'),
                        $text('Email', 'البريد الإلكتروني'),
                        $text('Priority', 'دعم بأولوية'),
                        $text('Priority', 'دعم بأولوية'),
                    )],
                    ['key' => 'onboarding-call', 'label' => $text('Onboarding call', 'مكالمة الإعداد الأولي'), 'cells' => $byPlan(false, false, false, false, true)],
                ],
            ],
        ];
    }

    /**
     * @return list<array{question: array<string, string>, answer: array<string, string>}>
     */
    private function faq(): array
    {
        return [
            [
                'question' => ['en' => 'Is the free plan really free?', 'ar' => 'هل باقة Free مجانية حقًا؟'],
                'answer' => [
                    'en' => 'Yes. No card, no time limit. You get one channel and up to {free_contacts} contacts, and you can stay on it as long as you like.',
                    'ar' => 'نعم. بدون بطاقة وبدون حد زمني. تحصل على قناة واحدة وما يصل إلى {free_contacts} جهة اتصال، ويمكنك البقاء عليها ما شئت.',
                ],
            ],
            [
                'question' => ['en' => 'What counts as a contact?', 'ar' => 'ما الذي يُحسب جهة اتصال؟'],
                'answer' => [
                    'en' => 'Anyone who has messaged you or commented and got a reply from Blexchat. Each person counts once, however many times they write.',
                    'ar' => 'أي شخص راسلك أو علّق وتلقى ردًا من Blexchat. يُحسب كل شخص مرة واحدة، مهما تكررت رسائله.',
                ],
            ],
            [
                'question' => ['en' => 'What happens when I reach my contact limit?', 'ar' => 'ماذا يحدث عندما أصل إلى الحد المسموح من جهات الاتصال؟'],
                'answer' => [
                    'en' => 'Nothing stops mid-conversation. We tell you before you get there, and you choose whether to move up a plan.',
                    'ar' => 'لا تتوقف أي محادثة في منتصفها. ننبّهك قبل أن تصل إليه، وأنت تقرر إن كنت تريد الترقية إلى باقة أعلى.',
                ],
            ],
            [
                'question' => ['en' => 'Can I change or cancel any time?', 'ar' => 'هل يمكنني تغيير باقتي أو إلغاؤها في أي وقت؟'],
                'answer' => [
                    'en' => 'Any time, from your account. Move up and the new plan starts straight away; move down or cancel and it applies from your next bill.',
                    'ar' => 'في أي وقت، من حسابك. عند الترقية تبدأ الباقة الجديدة فورًا، وعند التخفيض أو الإلغاء يُطبَّق التغيير من فاتورتك التالية.',
                ],
            ],
            [
                'question' => ['en' => 'What is the difference between monthly and annual?', 'ar' => 'ما الفرق بين الدفع الشهري والسنوي؟'],
                'answer' => [
                    'en' => 'The same plan, paid a year up front — and up to {max_saving}% less for it.',
                    'ar' => 'الباقة نفسها، تدفعها لسنة كاملة مقدمًا — وبسعر أقل بنسبة تصل إلى {max_saving}%.',
                ],
            ],
            [
                'question' => ['en' => 'Is there a free trial?', 'ar' => 'هل توجد تجربة مجانية؟'],
                'answer' => [
                    'en' => '{trial_plan} comes with {trial_days} days free. You are not charged until the trial ends, and we remind you before it does.',
                    'ar' => 'باقة {trial_plan} تأتي بتجربة مجانية مدتها {trial_days} يوم. لن تدفع شيئًا حتى تنتهي التجربة، وسنذكّرك قبل انتهائها.',
                ],
            ],
        ];
    }
}
