<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The referral program's page copy, and a "Referral program" link in the header and in the footer's
 * Account column, for a database seeded before the program existed. The links are only served while
 * the program is open, and it starts closed. Nothing is changed where an admin already added them.
 */
return new class extends Migration
{
    private const PAGE_URL = '/referral';

    public function up(): void
    {
        $this->addCopy();
        $this->addLink('navigation', ['items'], ['id' => 'referral', 'url' => self::PAGE_URL], ['en' => 'Referral program', 'ar' => 'برنامج الإحالة']);
        $this->addLink('footer', ['columns', 2, 'links'], ['url' => self::PAGE_URL], ['en' => 'Referral program', 'ar' => 'برنامج الإحالة']);
    }

    public function down(): void
    {
        DB::table('home_blocks')->where('key', 'referral_program')->delete();

        foreach (['navigation' => ['items'], 'footer' => ['columns', 2, 'links']] as $key => $path) {
            $block = DB::table('home_blocks')->where('key', $key)->first();
            $settings = json_decode((string) $block?->settings, true) ?? [];
            $index = $this->linkIndex(data_get($settings, $path, []));

            if ($block === null || $index === null) {
                continue;
            }

            $texts = json_decode((string) $block->texts, true) ?? [];
            $remove = function (array $node) use ($path, $index): array {
                $list = data_get($node, $path);

                if (is_array($list) && array_key_exists($index, $list)) {
                    unset($list[$index]);
                    data_set($node, $path, array_values($list));
                }

                return $node;
            };

            DB::table('home_blocks')->where('key', $key)->update([
                'settings' => json_encode($remove($settings), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'texts' => json_encode(array_map($remove, $texts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    private function addCopy(): void
    {
        // A fresh database gets the copy from HomeContentSeeder with the rest of the homepage.
        if (! DB::table('home_blocks')->where('key', 'navigation')->exists() || DB::table('home_blocks')->where('key', 'referral_program')->exists()) {
            return;
        }

        $texts = [
            'en' => [
                'title' => 'Earn up to {up_to} for every customer you bring',
                'lead' => 'Share your Blexchat link with your audience. When someone signs up through it and pays for a plan, you earn {terms}.',
                'cta' => ['label' => 'Get your link'],
                'steps' => [
                    ['title' => 'Sign up', 'text' => 'Create a free Blexchat account. Your link is waiting under Referrals.'],
                    ['title' => 'Share your link', 'text' => 'Put it in your bio, your stories or your DMs. A visit through it counts for {cookie_days} days.'],
                    ['title' => 'Earn', 'text' => 'When someone you sent pays for a plan, you earn {terms}.'],
                ],
                'benefits' => [
                    ['title' => '{rate}% commission', 'text' => 'On every plan, monthly or yearly.'],
                    ['title' => '{cookie_days}-day window', 'text' => 'Visitors who come back and sign up later still count for you.'],
                    ['title' => 'Paid every month', 'text' => 'Your earnings are paid out at the start of each month.'],
                    ['title' => 'Your own dashboard', 'text' => 'See sign-ups, paying customers and earnings as they happen.'],
                ],
                'earnings_heading' => 'What one sale earns you',
                'faq' => [
                    ['question' => 'Who can join?', 'answer' => 'Anyone with a Blexchat account, on any plan, including Free.'],
                    ['question' => 'How much can I earn?', 'answer' => 'You earn {terms}, with no limit on how many customers you bring.'],
                    ['question' => 'How and when am I paid?', 'answer' => '{payout_method}'],
                    ['question' => 'How long does my link count?', 'answer' => 'For {cookie_days} days after someone opens it, as long as they sign up in the same browser.'],
                    ['question' => 'Can I use my own link?', 'answer' => 'No. Only new accounts count, and never your own.'],
                ],
                'payout_method' => 'Earnings are paid by bank transfer at the start of each month.',
            ],
            'ar' => [
                'title' => 'اربح حتى {up_to} عن كل عميل تُحضره',
                'lead' => 'شارك رابط Blexchat الخاص بك مع جمهورك. عندما يسجّل أحدهم من خلاله ويدفع مقابل باقة، تربح {terms}.',
                'cta' => ['label' => 'احصل على رابطك'],
                'steps' => [
                    ['title' => 'سجّل', 'text' => 'أنشئ حسابًا مجانيًا في Blexchat، وستجد رابطك في صفحة الإحالات.'],
                    ['title' => 'شارك رابطك', 'text' => 'ضعه في البايو أو القصص أو الرسائل. الزيارة من خلاله تُحسب لمدة {cookie_days} يومًا.'],
                    ['title' => 'اربح', 'text' => 'عندما يدفع أي شخص أرسلته مقابل باقة، تربح {terms}.'],
                ],
                'benefits' => [
                    ['title' => 'عمولة {rate}%', 'text' => 'على كل الباقات، شهرية أو سنوية.'],
                    ['title' => 'مهلة {cookie_days} يومًا', 'text' => 'من يعود ويسجّل لاحقًا يُحسب لك أيضًا.'],
                    ['title' => 'دفعات كل شهر', 'text' => 'تُحوَّل أرباحك في بداية كل شهر.'],
                    ['title' => 'لوحتك الخاصة', 'text' => 'تابع التسجيلات والعملاء الدافعين والأرباح أولًا بأول.'],
                ],
                'earnings_heading' => 'كم تربح من كل عملية بيع',
                'faq' => [
                    ['question' => 'من يمكنه الانضمام؟', 'answer' => 'أي شخص لديه حساب في Blexchat، على أي باقة، بما فيها المجانية.'],
                    ['question' => 'كم يمكنني أن أربح؟', 'answer' => 'تربح {terms}، بدون حد لعدد العملاء الذين تُحضرهم.'],
                    ['question' => 'كيف ومتى أستلم أرباحي؟', 'answer' => '{payout_method}'],
                    ['question' => 'إلى متى يُحسب رابطي؟', 'answer' => 'لمدة {cookie_days} يومًا بعد فتح الرابط، بشرط أن يسجّل من نفس المتصفح.'],
                    ['question' => 'هل يمكنني استخدام رابطي لنفسي؟', 'answer' => 'لا. تُحسب الحسابات الجديدة فقط، وليس حسابك أنت.'],
                ],
                'payout_method' => 'تُحوَّل الأرباح بتحويل بنكي في بداية كل شهر.',
            ],
        ];

        DB::table('home_blocks')->insert([
            'key' => 'referral_program',
            'sort' => null,
            'texts' => json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'settings' => json_encode(['cta' => ['url' => '/signup']], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Appends a link to a list held half in settings (its url) and half in each language's texts (its label).
     *
     * @param  list<string|int>  $path
     * @param  array<string, string>  $settings
     * @param  array<string, string>  $labels
     */
    private function addLink(string $key, array $path, array $settings, array $labels): void
    {
        $block = DB::table('home_blocks')->where('key', $key)->first();

        if ($block === null) {
            return;
        }

        $storedSettings = json_decode((string) $block->settings, true) ?? [];
        $list = data_get($storedSettings, $path);

        if (! is_array($list) || $this->linkIndex($list) !== null) {
            return;
        }

        $index = count($list);
        data_set($storedSettings, [...$path, $index], $settings);

        $texts = json_decode((string) $block->texts, true) ?? [];

        foreach ($labels as $locale => $label) {
            if (isset($texts[$locale])) {
                data_set($texts[$locale], [...$path, $index, 'label'], $label);
            }
        }

        DB::table('home_blocks')->where('key', $key)->update([
            'settings' => json_encode($storedSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'texts' => json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $list
     */
    private function linkIndex(array $list): ?int
    {
        foreach ($list as $index => $item) {
            if (is_array($item) && ($item['url'] ?? null) === self::PAGE_URL) {
                return (int) $index;
            }
        }

        return null;
    }
};
