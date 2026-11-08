<?php

namespace Database\Seeders;

use App\Models\AutomationTemplate;
use Illuminate\Database\Seeder;

/**
 * The template picker (W19) and the wizard's starting answers per trigger, as the frontend has
 * them (src/pages/app/templatesContent.ts, src/pages/wizard/wizardSession.ts and wizardState.ts).
 * The copy is a placeholder until the client's template library arrives; admins edit it in the
 * panel. Safe to run again: rows are matched by key.
 */
class AutomationTemplateSeeder extends Seeder
{
    private const SAMPLE_LINK = 'https://nourboutique.com/abaya-winter';

    private const SHOP_LINK = 'https://nourboutique.com/shop';

    private const PUBLIC_REPLY = ['en' => 'We sent you the details in DM 💌', 'ar' => 'أرسلنا لك التفاصيل بالخاص 💌'];

    public function run(): void
    {
        foreach ([...$this->starters(), ...$this->templates()] as $sort => $template) {
            AutomationTemplate::updateOrCreate(['key' => $template['key']], [...$template, 'sort' => $sort]);
        }
    }

    /**
     * What the wizard opens with when no template is named: the automation's name in the list,
     * and the sample shop's starting answers.
     *
     * @return list<array<string, mixed>>
     */
    private function starters(): array
    {
        return [
            [
                'key' => 'starter-comment',
                'is_starter' => true,
                'name' => ['en' => 'Auto-DM links from comments', 'ar' => 'إرسال الروابط تلقائيًا لمن يعلّق'],
                'build' => 'quick',
                'trigger' => 'comment',
                'match' => 'words',
                'keywords' => ['en' => ['price'], 'ar' => ['السعر']],
                'public_reply' => self::PUBLIC_REPLY,
                'dm_text' => [
                    'en' => 'Hi there! 👋 Here is the winter abaya you asked about — 249 SAR. Sizes and ordering here:',
                    'ar' => 'هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال. المقاسات والطلب من هنا:',
                ],
                'dm_button' => ['en' => 'See the product', 'ar' => 'شوفي المنتج'],
                'link_url' => self::SAMPLE_LINK,
            ],
            [
                'key' => 'starter-story',
                'is_starter' => true,
                'name' => ['en' => 'Reply to story replies', 'ar' => 'الرد على ردود القصص'],
                'build' => 'quick',
                'trigger' => 'story',
                'match' => 'any',
                'dm_text' => [
                    'en' => 'So glad you replied 💜 Here is the piece from the story, with price and sizes:',
                    'ar' => 'يسعدنا ردّك 💜 هذه القطعة التي في الستوري، بالسعر والمقاسات:',
                ],
                'dm_button' => ['en' => 'See the piece', 'ar' => 'شاهدي القطعة'],
                'link_url' => self::SAMPLE_LINK,
            ],
            [
                'key' => 'starter-dm',
                'is_starter' => true,
                'name' => ['en' => 'Respond to all your DMs', 'ar' => 'الرد على كل رسائلك الخاصة'],
                'build' => 'quick',
                'trigger' => 'dm',
                'match' => 'any',
                'dm_text' => [
                    'en' => 'Hi there! 👋 We got your message and will answer soon. Meanwhile, every product and price is here:',
                    'ar' => 'أهلًا وسهلًا! 👋 وصلتنا رسالتك وسنرد عليك بأسرع وقت. وفي الأثناء، كل المنتجات والأسعار هنا:',
                ],
                'dm_button' => ['en' => 'Browse the shop', 'ar' => 'تصفّحي المتجر'],
                'link_url' => self::SHOP_LINK,
            ],
        ];
    }

    /**
     * The picker's shelf. Quick templates fill the wizard's trigger and message; flow templates
     * only name what they do.
     *
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 't1',
                'name' => ['en' => 'Comment a word → send the catalogue', 'ar' => 'تعليق بكلمة ← إرسال الكتالوج'],
                'line' => [
                    'en' => 'The one most shops start with. A word in the comments, a DM with the product link.',
                    'ar' => 'القالب الذي تبدأ به معظم المتاجر. كلمة في التعليقات، ورسالة خاصة فيها رابط المنتج.',
                ],
                'build' => 'quick',
                'goal' => 'sell',
                'trigger' => 'comment',
                'match' => 'words',
                'keywords' => ['en' => ['catalogue'], 'ar' => ['كتالوج']],
                'public_reply' => self::PUBLIC_REPLY,
                'dm_text' => [
                    'en' => 'Hi there! 👋 Here is the whole season’s catalogue, with prices and sizes:',
                    'ar' => 'أهلًا وسهلًا! 👋 هذا كتالوج الموسم كاملًا، بالأسعار والمقاسات:',
                ],
                'dm_button' => ['en' => 'Open the catalogue', 'ar' => 'افتحي الكتالوج'],
                'link_url' => self::SAMPLE_LINK,
            ],
            [
                'key' => 't2',
                'name' => ['en' => 'Story reply → thank you and a link', 'ar' => 'رد على القصة ← شكرًا ورابط'],
                'line' => [
                    'en' => 'Anyone who replies to a story gets a warm answer and somewhere to go.',
                    'ar' => 'كل من يرد على قصة يحصل على رد ودود ومكان يذهب إليه.',
                ],
                'build' => 'quick',
                'goal' => 'sell',
                'trigger' => 'story',
                'match' => 'any',
                'dm_text' => [
                    'en' => 'So glad you replied 💜 Here is the piece from the story, with price and sizes:',
                    'ar' => 'يسعدنا ردّك 💜 هذه القطعة التي في الستوري، بالسعر والمقاسات:',
                ],
                'dm_button' => ['en' => 'See the piece', 'ar' => 'شاهدي القطعة'],
                'link_url' => self::SAMPLE_LINK,
            ],
            [
                'key' => 't3',
                'name' => ['en' => 'First DM → welcome and menu', 'ar' => 'أول رسالة خاصة ← ترحيب وقائمة'],
                'line' => [
                    'en' => 'A first-time DM gets a greeting and three buttons instead of silence.',
                    'ar' => 'أول رسالة خاصة من شخص جديد تحصل على تحية وثلاثة أزرار بدل الصمت.',
                ],
                'build' => 'quick',
                'goal' => 'support',
                'trigger' => 'dm',
                'match' => 'any',
                'dm_text' => [
                    'en' => 'Hi there! 👋 Welcome. You can browse every product and price from here:',
                    'ar' => 'أهلًا وسهلًا! 👋 سعدنا بزيارتك. يمكنكِ تصفّح كل المنتجات والأسعار من هنا:',
                ],
                'dm_button' => ['en' => 'Browse the shop', 'ar' => 'تصفّحي المتجر'],
                'link_url' => self::SHOP_LINK,
            ],
            [
                'key' => 't4',
                'name' => ['en' => 'Comment → ask for the email, then send', 'ar' => 'تعليق ← طلب البريد الإلكتروني ثم الإرسال'],
                'line' => [
                    'en' => 'Collect the address before the link, so the contact outlives the sale.',
                    'ar' => 'اجمع العنوان قبل الرابط، لتبقى جهة الاتصال بعد انتهاء البيع.',
                ],
                'build' => 'flow',
                'locked' => true,
                'goal' => 'grow',
                'trigger' => 'comment',
            ],
            [
                'key' => 't5',
                'name' => ['en' => 'Abandoned chat → follow up after a day', 'ar' => 'محادثة متروكة ← متابعة بعد يوم'],
                'line' => [
                    'en' => 'Nudge the people who opened the DM and never tapped the button.',
                    'ar' => 'ذكّر من فتحوا الرسالة الخاصة ولم يضغطوا على الزر.',
                ],
                'build' => 'flow',
                'locked' => true,
                'goal' => 'sell',
                'trigger' => 'dm',
            ],
            [
                'key' => 't6',
                'name' => ['en' => 'Common questions → instant answers', 'ar' => 'الأسئلة الشائعة ← إجابات فورية'],
                'line' => [
                    'en' => 'Price, delivery and sizes answered the moment they are asked.',
                    'ar' => 'السعر والتوصيل والمقاسات، يُجاب عنها لحظة السؤال.',
                ],
                'build' => 'quick',
                'goal' => 'support',
                'trigger' => 'dm',
                'match' => 'words',
                'keywords' => ['en' => ['price', 'delivery', 'size'], 'ar' => ['السعر', 'توصيل', 'مقاس']],
                'dm_text' => [
                    'en' => 'Hi! Prices, sizes and delivery times are all on this page:',
                    'ar' => 'أهلًا! الأسعار والمقاسات ومدة التوصيل كلها في هذه الصفحة:',
                ],
                'dm_button' => ['en' => 'Common questions', 'ar' => 'الأسئلة الشائعة'],
                'link_url' => self::SHOP_LINK,
            ],
            [
                'key' => 't7',
                'name' => ['en' => 'Booking request → collect the details', 'ar' => 'طلب حجز ← جمع التفاصيل'],
                'line' => [
                    'en' => 'Name, day and service, then hand the conversation to you.',
                    'ar' => 'الاسم واليوم والخدمة، ثم تُحوَّل المحادثة إليك.',
                ],
                'build' => 'flow',
                'goal' => 'book',
                'trigger' => 'dm',
            ],
            [
                'key' => 't8',
                'name' => ['en' => 'Ad click → qualify, then reply', 'ar' => 'نقرة على إعلان ← تأهيل ثم رد'],
                'line' => [
                    'en' => 'People arriving from a click-to-message ad get a question first.',
                    'ar' => 'من يصل من إعلان النقر للمراسلة يُسأل سؤالًا أولًا.',
                ],
                'build' => 'flow',
                'locked' => true,
                'goal' => 'grow',
                'trigger' => 'ad',
            ],
            [
                'key' => 't9',
                'name' => ['en' => 'Story reply → join the waitlist', 'ar' => 'رد على القصة ← الانضمام إلى قائمة الانتظار'],
                'line' => [
                    'en' => 'Turn a restock story into a list you can message when it lands.',
                    'ar' => 'حوّل قصة عودة المنتج إلى قائمة تراسلها عند وصوله.',
                ],
                'build' => 'quick',
                'goal' => 'grow',
                'trigger' => 'story',
                'match' => 'any',
                'dm_text' => [
                    'en' => 'Welcome! 💜 Join the waitlist and we will tell you the moment it lands:',
                    'ar' => 'أهلًا بكِ! 💜 سجّلي في قائمة الانتظار ونخبركِ فور توفّره:',
                ],
                'dm_button' => ['en' => 'Sign me up', 'ar' => 'سجّليني'],
                'link_url' => 'https://nourboutique.com/waitlist',
            ],
        ];
    }
}
