<?php

namespace Database\Seeders;

use App\Models\ComparisonPoint;
use App\Models\DemoReplyRule;
use App\Models\FaqItem;
use App\Models\HomeBlock;
use App\Models\HomeStat;
use App\Models\UseCase;
use Illuminate\Database\Seeder;

/**
 * Resets the marketing homepage to its default English and Arabic content. Running it replaces
 * whatever was edited in the admin panel, so it is meant for fresh installs and tests.
 */
class HomeContentSeeder extends Seeder
{
    /** Keys whose string values are the same in every language (ids, routes, frontend switches). */
    private const SHARED_KEYS = ['id', 'type', 'channel', 'shape', 'tone', 'loop', 'slug', 'url', 'menu', 'icon', 'code'];

    public function run(): void
    {
        $this->seedBlocks();
        $this->seedLists();
    }

    private function seedBlocks(): void
    {
        $image = fn (string $path): string => 'marketing/home/'.$path;

        $blocks = [
            'hero' => [
                'id' => 'hero', 'type' => 'hero',
                'headline_rotations' => [['Reach', 'Millions'], ['Sell in', 'every DM'], ['Reply to', 'everyone']],
                'handwriting' => 'automatically.',
                'photos' => ['relax' => $image('hero-photo.webp'), 'celebrate' => $image('hero-photo-celebrate.webp')],
                'story' => [
                    'pressure' => ['counter' => 'new messages', 'counter_one' => 'new message', 'counter_other' => 'new messages', 'count' => 47, 'more' => '+44 more', 'chips' => [
                        ['id' => 'c1', 'handle' => 'sara.styles', 'text' => 'How much is this? 😍'],
                        ['id' => 'c2', 'handle' => 'omar.fit', 'text' => 'Do you ship to Riyadh?'],
                        ['id' => 'c3', 'handle' => 'lina.m', 'text' => 'LINK PLEASE 🙏'],
                    ]],
                    'working_badge' => 'BLEXCHAT is replying',
                    'relief' => ['stats' => [
                        ['id' => 'replies', 'to' => 47, 'suffix' => '', 'label' => 'replies', 'label_one' => 'reply', 'label_other' => 'replies'],
                        ['id' => 'orders', 'to' => 12, 'suffix' => '', 'label' => 'orders', 'label_one' => 'order', 'label_other' => 'orders'],
                        ['id' => 'saved', 'to' => 3, 'suffix' => 'h', 'label' => 'saved', 'label_one' => 'saved', 'label_other' => 'saved'],
                    ]],
                    'visitor_result' => 'Replied in 0.4s',
                ],
                'composer' => ['label' => 'Try it live', 'placeholder_prefix' => 'Write a comment…', 'placeholder' => 'Write a comment… e.g. How much?', 'sr_label' => 'Try a comment and see the automatic reply', 'send_label' => 'Send comment', 'examples' => ['How much?', 'Do you ship to Riyadh?', 'Link please 🙏']],
                'subtitle' => 'BLEXCHAT turns comments and DMs into customers.',
                'description' => 'Smart automations that sell and reply for you, 24/7.',
                'cta' => ['label' => 'Get started free', 'url' => '/signup'],
                'microcopy' => 'Free plan · No credit card required',
                'cutout' => $image('hero-person-upper.webp'),
                'social_proof' => ['text' => 'Built for Instagram, WhatsApp, TikTok & Messenger'],
                'channels_label' => 'Works with',
                'channels' => [['id' => 'instagram', 'label' => 'Instagram'], ['id' => 'whatsapp', 'label' => 'WhatsApp'], ['id' => 'tiktok', 'label' => 'TikTok'], ['id' => 'messenger', 'label' => 'Messenger']],
                'conversations' => [
                    ['id' => 'sara', 'channel' => 'instagram', 'handle' => 'sara.styles', 'initial' => 'S', 'avatar' => $image('avatars/sara.webp'), 'question' => 'How much is this? 😍', 'reply' => 'Hey Sara! Just sent the price to your DMs 💌', 'action' => 'View product'],
                    ['id' => 'omar', 'channel' => 'whatsapp', 'handle' => 'omar.fit', 'initial' => 'O', 'avatar' => $image('avatars/omar.webp'), 'question' => 'Do you ship to Riyadh?', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order'],
                    ['id' => 'lina', 'channel' => 'tiktok', 'handle' => 'lina.m', 'initial' => 'L', 'avatar' => $image('avatars/lina.webp'), 'question' => 'LINK PLEASE 🙏', 'reply' => 'Sent! Check your DMs ✨', 'action' => 'Open link', 'result' => '✓ New order · $49'],
                ],
            ],
            'creators' => ['id' => 'creators', 'type' => 'creator_strip', 'label' => 'Built for creators, brands and sellers'],
            'intro' => ['id' => 'intro', 'type' => 'intro', 'headline' => ['Scale up your best', 'conversations'], 'lead' => 'Powerful automations for every way you sell, reply and grow.'],
            'features' => ['id' => 'features', 'type' => 'vertical_slider', 'slides_label' => 'Feature slides', 'slides' => [
                ['id' => 'comments', 'index' => '01', 'headline' => 'Turn comments into customers', 'body' => '"How much is this?" → instant reply, and the sale happens while you sleep.', 'cta' => ['label' => 'See how', 'url' => '#see-it-in-action?tab=comment'], 'phone_label' => 'blexchat.store', 'channel' => 'instagram', 'scene' => ['post' => 'New drop · 2h', 'post_photo' => $image('scenes/nora.webp'), 'comments' => [['handle' => 'sara.styles', 'text' => 'How much is this? 😍'], ['handle' => 'noura.k', 'text' => 'Is it still available?'], ['handle' => 'omar.fit', 'text' => 'Link please 🙏']], 'reply' => 'Sent you the price and the link in DMs 💌', 'action' => 'View product', 'order' => 'New order · $49']],
                ['id' => 'dms', 'index' => '02', 'headline' => 'Never leave a DM unanswered', 'body' => 'Smart replies handle FAQs, capture phone and email, and hand over to you only when it matters.', 'cta' => ['label' => 'See how', 'url' => '#see-it-in-action?tab=faq'], 'phone_label' => 'Direct', 'channel' => 'whatsapp', 'scene' => ['handle' => 'sara.styles', 'avatar' => $image('avatars/sara.webp'), 'question' => 'Do you ship to Riyadh?', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order', 'seen' => 'Seen', 'lead' => ['name' => 'Sara A.', 'detail' => '+966 5• ••• 4412 · sara@mail.com', 'tag' => 'Lead captured']]],
                ['id' => 'stories', 'index' => '03', 'headline' => 'Reply to every story mention', 'body' => 'Turn reactions into conversations automatically, 24/7.', 'cta' => ['label' => 'See how', 'url' => '#try-it'], 'phone_label' => 'Your story', 'channel' => 'instagram', 'scene' => ['story' => 'Reacted to your story', 'photo' => $image('scenes/lina.webp'), 'handle' => 'lina.m', 'reaction' => '❤️', 'reply' => 'Thank you Lina! Here is the link ✨', 'action' => 'Open link']],
                ['id' => 'channels', 'index' => '04', 'headline' => 'Grow across every channel', 'body' => 'Instagram, WhatsApp, TikTok, Messenger — one inbox, one set of automations.', 'cta' => ['label' => 'See how', 'url' => '/signup'], 'phone_label' => 'All inboxes', 'channel' => 'messenger', 'scene' => ['inbox' => 'One inbox', 'total' => '128', 'total_label' => 'conversations today', 'tiles' => [['id' => 'instagram', 'count' => '64'], ['id' => 'whatsapp', 'count' => '38'], ['id' => 'tiktok', 'count' => '19'], ['id' => 'messenger', 'count' => '7']], 'footer' => 'All answered automatically']],
            ]],
            'autopilot' => ['id' => 'autopilot', 'type' => 'giant_word', 'kicker' => 'This is what we call', 'word' => ['Auto', 'pilot'], 'mark' => '24/7', 'photo' => $image('hero-photo-celebrate.webp')],
            'use-cases' => ['id' => 'use-cases', 'type' => 'use_cases', 'heading' => 'Built for the way YOU sell', 'cta_label' => 'See how', 'prev_label' => 'Previous use cases', 'next_label' => 'Next use cases'],
            'before-after' => ['id' => 'before-after', 'type' => 'comparison', 'heading' => 'Your inbox: a before & after', 'before' => ['title' => 'Before BLEXCHAT — all work, no play'], 'after' => ['title' => 'After BLEXCHAT — less grind, more pay'], 'cta' => ['label' => 'Get started free', 'url' => '/signup']],
            'see-it-in-action' => ['id' => 'see-it-in-action', 'type' => 'flow_tabs', 'heading' => 'See it in action', 'lead' => 'Pick a flow — watch BLEXCHAT run it.', 'composer_placeholder' => 'Message…', 'tabs_label' => 'Flows', 'tabs' => [
                ['id' => 'follow', 'title' => 'Say hi to new followers', 'description' => 'A warm DM the moment they follow.', 'surface' => 'Instagram · Direct', 'sr' => 'On Instagram, a new follower gets a welcome DM straight away and joins the drop list.', 'scene' => ['event' => 'sara.styles started following you', 'avatar' => $image('avatars/sara.webp'), 'reply' => "Hey Sara 👋 thanks for the follow! Want first dibs on Thursday's drop?", 'action' => 'Yes, add me', 'result' => '✓ New subscriber']],
                ['id' => 'welcome', 'title' => 'Welcome messages', 'description' => 'Greet every first message instantly.', 'surface' => 'WhatsApp · Business', 'sr' => 'On WhatsApp, a first message is greeted instantly and the contact is saved as a lead.', 'scene' => ['handle' => 'Sara', 'question' => 'Salam! Are you open today?', 'avatar' => $image('avatars/sara.webp'), 'reply' => 'Wa alaikum salam Sara! Open 10am–11pm. What can we get you?', 'action' => 'See the menu', 'lead' => ['tag' => 'New lead saved', 'name' => 'Sara K.', 'detail' => '+971 5X XXX XXXX · WhatsApp']]],
                ['id' => 'faq', 'title' => 'Automate FAQs', 'description' => 'Price, delivery, sizes — answered in seconds.', 'surface' => 'Instagram · Story replies', 'sr' => 'A story reply asking the price is answered in seconds and turns into an order.', 'scene' => ['event' => 'Replied to your story', 'handle' => 'lina.k', 'question' => 'How much is this? 😍', 'avatar' => $image('avatars/lina.webp'), 'reply' => "It's 180 AED — sizes S to XL, delivered in 2 days.", 'action' => 'Order now', 'result' => '✓ Order #1042 placed']],
                ['id' => 'comment', 'title' => 'Auto-DM from comments', 'description' => 'Comment ‘LINK’ → the link lands in DMs.', 'surface' => 'Instagram · Comments → DM', 'sr' => 'Someone comments LINK under a post and the link lands in their DMs.', 'scene' => ['handle' => 'khalid.r', 'question' => 'LINK', 'avatar' => $image('avatars/khalid.webp'), 'transition' => '↓ sent to DMs', 'reply' => 'Here you go, Khalid 🔗 the full guide is one tap away.', 'action' => 'Open the link', 'result' => '✓ Link delivered in 0.4s']],
                ['id' => 'giveaway', 'title' => 'Run giveaways', 'description' => 'Collect entries and pick winners automatically.', 'surface' => 'TikTok · Giveaway', 'sr' => 'On TikTok, every giveaway comment is confirmed as an entry and counted.', 'scene' => ['handle' => 'hala.m', 'question' => 'Count me in! 🎉', 'reply' => "You're in, Hala! Winners are picked Friday at 8pm.", 'action' => 'Share for 2× entries', 'counter' => ['channel' => 'tiktok', 'value' => '1,284', 'label' => 'Entries collected']]],
            ]],
            'try-it' => ['id' => 'try-it', 'type' => 'live_demo', 'kicker' => 'No signup needed', 'heading_lead' => "Don't take our word for it.", 'heading_mark' => 'Try it.', 'lead' => 'Write a comment like your customers do — watch BLEXCHAT answer it.', 'placeholder' => 'Write a comment…', 'bridge' => 'That flow took 0 minutes to build. Yours takes 2.', 'cta' => ['label' => 'Build your first flow', 'url' => '/signup'], 'handle' => 'you', 'initial' => 'Y', 'suggestions' => ['How much is this? 😍', 'Do you ship to Riyadh?', 'Link please 🙏']],
            'get-started' => ['id' => 'get-started', 'type' => 'steps', 'heading' => 'Live in minutes, not weeks.', 'steps' => [['number' => '01', 'name' => 'Sign up free', 'line' => 'No credit card, no calls.', 'loop' => 'tick'], ['number' => '02', 'name' => 'Connect your channels', 'line' => 'Instagram, WhatsApp, TikTok, Messenger — one tap each.', 'loop' => 'channels'], ['number' => '03', 'name' => 'Go live', 'line' => 'Pick a flow, switch it on, watch replies happen.', 'loop' => 'switch']], 'cta' => ['label' => 'Get started free', 'url' => '/signup'], 'secondary_cta' => ['label' => 'See plans', 'url' => '/pricing']],
            'numbers' => ['id' => 'numbers', 'type' => 'numbers', 'heading' => 'BLEXCHAT in numbers'],
            'faq' => ['id' => 'faq', 'type' => 'faq', 'heading' => 'Questions, answered.'],
            'navigation' => [
                'items' => [
                    ['id' => 'product', 'label' => 'Product', 'menu' => 'product'],
                    ['id' => 'solutions', 'label' => 'Solutions', 'menu' => 'solutions'],
                    ['id' => 'agencies', 'label' => 'Agencies', 'url' => '/solutions/agencies'],
                    ['id' => 'pricing', 'label' => 'Pricing', 'url' => '/pricing'],
                ],
                'menus' => [
                    'product' => ['heading' => 'Automate every conversation', 'items' => [
                        ['id' => 'instagram', 'title' => 'Instagram', 'desc' => 'Turn comments and DMs into customers', 'channel' => 'instagram', 'url' => '/signup'],
                        ['id' => 'whatsapp', 'title' => 'WhatsApp', 'desc' => 'Answer every chat, day and night', 'channel' => 'whatsapp', 'url' => '/signup'],
                        ['id' => 'messenger', 'title' => 'Messenger', 'desc' => 'Automate your Facebook Page inbox', 'channel' => 'messenger', 'url' => '/signup'],
                        ['id' => 'tiktok', 'title' => 'TikTok', 'desc' => 'Turn comments on your videos into orders', 'channel' => 'tiktok', 'url' => '/signup'],
                        ['id' => 'ai', 'title' => 'Blexchat AI', 'desc' => 'Replies that know your catalogue', 'icon' => 'spark', 'url' => '#try-it', 'cta' => 'Try it live'],
                    ]],
                    'solutions' => ['heading' => 'Built for how you sell', 'columns' => true, 'items' => [
                        ['id' => 'creators', 'title' => 'Creators', 'desc' => 'Turn followers into a subscriber list.', 'icon' => 'camera', 'url' => '/solutions/creators'],
                        ['id' => 'e-commerce', 'title' => 'E-commerce', 'desc' => 'Recover carts and confirm orders in DM.', 'icon' => 'bag', 'url' => '/solutions/e-commerce'],
                        ['id' => 'restaurants', 'title' => 'Restaurants', 'desc' => 'Bookings and orders from DMs.', 'icon' => 'cup', 'url' => '/solutions/restaurants'],
                        ['id' => 'clinics', 'title' => 'Clinics', 'desc' => 'Appointments booked while you work.', 'icon' => 'tooth', 'url' => '/solutions/clinics'],
                        ['id' => 'agencies', 'title' => 'Agencies', 'desc' => 'Run many client accounts from one seat.', 'icon' => 'briefcase', 'url' => '/solutions/agencies'],
                        ['id' => 'real-estate', 'title' => 'Real estate', 'desc' => 'Qualify buyers before the first call.', 'icon' => 'house', 'url' => '/solutions/real-estate'],
                        ['id' => 'education', 'title' => 'Education', 'desc' => 'Enrol students without a landing page.', 'icon' => 'cap', 'url' => '/solutions/education'],
                        ['id' => 'support', 'title' => 'Support', 'desc' => 'Deflect repeat questions around the clock.', 'icon' => 'headset', 'url' => '/solutions/support'],
                    ]],
                ],
                'sign_in' => ['label' => 'Sign in', 'url' => '/signin'],
                'get_started' => ['label' => 'Get started', 'url' => '/signup'],
                'get_started_mobile' => ['label' => 'Get started free', 'url' => '/signup'],
                'aria' => ['main' => 'Main', 'mobile' => 'Mobile', 'open_menu' => 'Open menu', 'close_menu' => 'Close menu'],
            ],
            'footer' => [
                'tagline' => 'Turn comments and DMs into customers.',
                'cta' => ['label' => 'Get started free', 'url' => '/signup'],
                'columns' => [
                    ['title' => 'Product', 'links' => [['label' => 'Features', 'url' => '#features'], ['label' => 'Pricing', 'url' => '/pricing'], ['label' => 'Blexchat AI', 'url' => '#try-it']]],
                    ['title' => 'Solutions', 'links' => [['label' => 'Creators', 'url' => '/solutions/creators'], ['label' => 'E-commerce', 'url' => '/solutions/e-commerce'], ['label' => 'Restaurants', 'url' => '/solutions/restaurants'], ['label' => 'Agencies', 'url' => '/solutions/agencies']]],
                    ['title' => 'Account', 'links' => [['label' => 'Sign up', 'url' => '/signup'], ['label' => 'Sign in', 'url' => '/signin']]],
                ],
                'made_for' => 'Made for',
                'made_for_sr' => 'Made for creators, brands, sellers and agencies',
                'giant_words' => ['Creators', 'Brands', 'Sellers', 'Agencies'],
                'copyright' => '© 2026 BLEXCHAT',
                'legal' => [['id' => 'privacy', 'label' => 'Privacy', 'url' => '/legal/privacy'], ['id' => 'terms', 'label' => 'Terms', 'url' => '/legal/terms']],
                'aria' => ['footer' => 'Footer', 'home' => 'Blexchat home'],
            ],
            'not_found' => [
                'question' => 'Is this page still here?',
                'replied' => 'Replied in 0.4s',
                'answer' => 'It isn’t, but the rest of the site is. The way home is below.',
                'code' => '404',
                'title' => 'This page doesn’t exist.',
                'body' => 'The link may be old, or the address has a typo.',
                'cta' => ['label' => 'Back to the homepage', 'url' => '/'],
            ],
            'demo_replies' => [
                'card' => ['label' => 'Auto-reply', 'timer' => '0.4s'],
                'fallback' => ['id' => 'fallback', 'reply' => 'Got it — sending you the details right now 💌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'],
            ],
        ];

        $sections = ['hero', 'creators', 'intro', 'features', 'autopilot', 'use-cases', 'before-after', 'see-it-in-action', 'try-it', 'get-started', 'numbers', 'faq'];

        $arabic = $this->arabicBlockTexts();

        foreach ($blocks as $key => $content) {
            [$texts, $settings] = $this->split($content);
            $sort = array_search($key, $sections, true);

            HomeBlock::updateOrCreate(['key' => $key], [
                'sort' => $sort === false ? null : $sort + 1,
                'texts' => ['en' => $texts, 'ar' => $arabic[$key]],
                'settings' => $settings,
            ]);
        }
    }

    /**
     * Arabic copy of every block, in the same shape as the English texts.
     *
     * @return array<string, array<string, mixed>>
     */
    private function arabicBlockTexts(): array
    {
        $comments = ['price' => 'بكم هذي؟ 😍', 'shipping' => 'توصلون للرياض؟', 'link' => 'الرابط لو سمحت 🙏'];

        return [
            'hero' => [
                'headline_rotations' => [['اوصل', 'للملايين'], ['بِع في', 'كل رسالة'], ['رُد على', 'الجميع']],
                'handwriting' => 'تلقائياً.',
                'story' => [
                    'pressure' => ['counter' => 'رسالة جديدة', 'counter_zero' => 'رسالة جديدة', 'counter_one' => 'رسالة جديدة', 'counter_two' => 'رسالتان جديدتان', 'counter_few' => 'رسائل جديدة', 'counter_many' => 'رسالة جديدة', 'counter_other' => 'رسالة جديدة', 'more' => '+44 أخرى', 'chips' => [
                        ['handle' => 'sara.styles', 'text' => $comments['price']],
                        ['handle' => 'omar.fit', 'text' => $comments['shipping']],
                        ['handle' => 'lina.m', 'text' => $comments['link']],
                    ]],
                    'working_badge' => 'بلكس تشات يرد الآن',
                    'relief' => ['stats' => [
                        ['suffix' => '', 'label' => 'رد', 'label_zero' => 'رد', 'label_one' => 'رد', 'label_two' => 'ردّان', 'label_few' => 'ردود', 'label_many' => 'ردًا', 'label_other' => 'رد'],
                        ['suffix' => '', 'label' => 'طلب', 'label_zero' => 'طلب', 'label_one' => 'طلب', 'label_two' => 'طلبان', 'label_few' => 'طلبات', 'label_many' => 'طلبًا', 'label_other' => 'طلب'],
                        ['suffix' => 'س', 'label' => 'موفّرة', 'label_zero' => 'موفّرة', 'label_one' => 'موفّرة', 'label_two' => 'موفّرة', 'label_few' => 'موفّرة', 'label_many' => 'موفّرة', 'label_other' => 'موفّرة'],
                    ]],
                    'visitor_result' => 'تم الرد خلال 0.4 ثانية',
                ],
                'composer' => ['label' => 'جرّبه الآن', 'placeholder_prefix' => 'اكتب تعليقاً…', 'placeholder' => 'اكتب تعليقاً… مثلاً: بكم؟', 'sr_label' => 'جرّب تعليقاً وشاهد الرد التلقائي', 'send_label' => 'إرسال التعليق', 'examples' => ['بكم؟', 'توصلون للرياض؟', 'الرابط لو سمحت 🙏']],
                'subtitle' => 'بلكس تشات يحوّل التعليقات والرسائل إلى عملاء.',
                'description' => 'أتمتة ذكية تبيع وترد عنك على مدار الساعة.',
                'cta' => ['label' => 'ابدأ مجاناً'],
                'microcopy' => 'خطة مجانية · بدون بطاقة ائتمان',
                'social_proof' => ['text' => 'مصمَّم لـ Instagram وWhatsApp وTikTok وMessenger'],
                'channels_label' => 'يعمل مع',
                'channels' => [['label' => 'إنستغرام'], ['label' => 'واتساب'], ['label' => 'تيك توك'], ['label' => 'ماسنجر']],
                'conversations' => [
                    ['handle' => 'sara.styles', 'initial' => 'س', 'question' => $comments['price'], 'reply' => 'هلا سارة! أرسلنا لك السعر بالخاص 💌', 'action' => 'شوفي المنتج'],
                    ['handle' => 'omar.fit', 'initial' => 'ع', 'question' => $comments['shipping'], 'reply' => 'أكيد! التوصيل مجاني لكل مناطق المملكة 🚚', 'action' => 'تتبّع الطلب'],
                    ['handle' => 'lina.m', 'initial' => 'ل', 'question' => 'الرابط لو سمحتي 🙏', 'reply' => 'أرسلناه! شيكي رسائلك ✨', 'action' => 'افتحي الرابط', 'result' => '✓ طلب جديد · 49$'],
                ],
            ],
            'creators' => ['label' => 'مصمَّم لصنّاع المحتوى والعلامات التجارية والبائعين'],
            'intro' => ['headline' => ['ضاعف أفضل', 'محادثاتك'], 'lead' => 'أتمتة قوية لكل طريقة تبيع بها وترد وتنمو.'],
            'features' => ['slides_label' => 'شرائح الميزات', 'slides' => [
                ['index' => '01', 'headline' => 'حوّل التعليقات إلى عملاء', 'body' => '"بكم هذي؟" ← رد فوري، والبيع يتم وأنت نايم.', 'cta' => ['label' => 'شاهد كيف'], 'phone_label' => 'blexchat.store', 'scene' => [
                    'post' => 'تشكيلة جديدة · قبل ساعتين',
                    'comments' => [['handle' => 'sara.styles', 'text' => $comments['price']], ['handle' => 'noura.k', 'text' => 'لسا متوفرة؟'], ['handle' => 'omar.fit', 'text' => $comments['link']]],
                    'reply' => 'أرسلنا لك السعر والرابط بالخاص 💌', 'action' => 'شوف المنتج', 'order' => 'طلب جديد · 49$',
                ]],
                ['index' => '02', 'headline' => 'لا تترك رسالة بدون رد', 'body' => 'ردود ذكية تجاوب على الأسئلة المتكررة، تجمع الجوال والإيميل، وتحوّل المحادثة لك فقط عند الحاجة.', 'cta' => ['label' => 'شاهد كيف'], 'phone_label' => 'الرسائل', 'scene' => [
                    'handle' => 'sara.styles', 'question' => $comments['shipping'], 'reply' => 'أكيد! التوصيل مجاني لكل مناطق المملكة 🚚', 'action' => 'تتبّع الطلب', 'seen' => 'شوهدت',
                    'lead' => ['name' => 'سارة أ.', 'detail' => '+966 5• ••• 4412 · sara@mail.com', 'tag' => 'تم حفظ عميل محتمل'],
                ]],
                ['index' => '03', 'headline' => 'رد على كل إشارة في الستوري', 'body' => 'حوّل التفاعلات إلى محادثات تلقائياً، على مدار الساعة.', 'cta' => ['label' => 'شاهد كيف'], 'phone_label' => 'الستوري', 'scene' => [
                    'story' => 'تفاعل مع الستوري', 'handle' => 'lina.m', 'reaction' => '❤️', 'reply' => 'شكراً لينا! هذا الرابط ✨', 'action' => 'افتحي الرابط',
                ]],
                ['index' => '04', 'headline' => 'انمُ عبر كل القنوات', 'body' => 'إنستغرام وواتساب وتيك توك وماسنجر — صندوق واحد، ومجموعة أتمتة واحدة.', 'cta' => ['label' => 'شاهد كيف'], 'phone_label' => 'كل الصناديق', 'scene' => [
                    'inbox' => 'صندوق واحد', 'total' => '128', 'total_label' => 'محادثة اليوم',
                    'tiles' => [['count' => '64'], ['count' => '38'], ['count' => '19'], ['count' => '7']],
                    'footer' => 'تم الرد على الجميع تلقائياً',
                ]],
            ]],
            'autopilot' => ['kicker' => 'هذا ما نسمّيه', 'word' => ['الطيار', 'الآلي'], 'mark' => '24/7'],
            'use-cases' => ['heading' => 'مصمّم لطريقتك في البيع', 'cta_label' => 'شاهد كيف', 'prev_label' => 'حالات الاستخدام السابقة', 'next_label' => 'حالات الاستخدام التالية'],
            'before-after' => ['heading' => 'صندوق رسائلك: قبل وبعد', 'before' => ['title' => 'قبل بلكس تشات — شغل بلا راحة'], 'after' => ['title' => 'بعد بلكس تشات — جهد أقل ودخل أكثر'], 'cta' => ['label' => 'ابدأ مجاناً']],
            'see-it-in-action' => ['heading' => 'شاهده يعمل', 'lead' => 'اختر مساراً — وشاهد بلكس تشات ينفّذه.', 'composer_placeholder' => 'رسالة…', 'tabs_label' => 'المسارات', 'tabs' => [
                ['title' => 'رحّب بالمتابعين الجدد', 'description' => 'رسالة دافئة لحظة المتابعة.', 'surface' => 'إنستغرام · الرسائل', 'sr' => 'على إنستغرام، يتلقى المتابع الجديد رسالة ترحيب فوراً وينضم لقائمة الإطلاقات.', 'scene' => [
                    'event' => 'sara.styles بدأت متابعتك', 'reply' => 'هلا سارة 👋 شكراً على المتابعة! تبين تكونين أول من يشوف تشكيلة الخميس؟', 'action' => 'إيه، ضيفيني', 'result' => '✓ مشتركة جديدة',
                ]],
                ['title' => 'رسائل الترحيب', 'description' => 'رحّب بكل أول رسالة فوراً.', 'surface' => 'واتساب · بزنس', 'sr' => 'على واتساب، يُرحَّب بأول رسالة فوراً ويُحفظ صاحبها كعميل محتمل.', 'scene' => [
                    'handle' => 'سارة', 'question' => 'السلام عليكم! فاتحين اليوم؟', 'reply' => 'وعليكم السلام يا سارة! فاتحين من 10 الصبح لين 11 الليل. وش نجهّز لك؟', 'action' => 'شوف المنيو',
                    'lead' => ['tag' => 'تم حفظ عميل جديد', 'name' => 'سارة ك.', 'detail' => '+971 5X XXX XXXX · واتساب'],
                ]],
                ['title' => 'أتمتة الأسئلة الشائعة', 'description' => 'السعر والتوصيل والمقاسات — يُجاب عنها في ثوانٍ.', 'surface' => 'إنستغرام · ردود الستوري', 'sr' => 'رد على ستوري يسأل عن السعر يُجاب عنه في ثوانٍ ويتحوّل إلى طلب.', 'scene' => [
                    'event' => 'ردّت على الستوري', 'handle' => 'lina.k', 'question' => $comments['price'], 'reply' => 'سعرها 180 درهم — المقاسات من S إلى XL، والتوصيل خلال يومين.', 'action' => 'اطلبي الآن', 'result' => '✓ تم الطلب رقم 1042',
                ]],
                ['title' => 'رسالة تلقائية من التعليقات', 'description' => 'علّق بكلمة "رابط" ← يوصلك الرابط بالخاص.', 'surface' => 'إنستغرام · من التعليقات إلى الخاص', 'sr' => 'يعلّق أحدهم بكلمة "رابط" تحت منشور فيصله الرابط في رسائله.', 'scene' => [
                    'handle' => 'khalid.r', 'question' => 'رابط', 'transition' => '↓ أُرسل إلى الخاص', 'reply' => 'تفضّل يا خالد 🔗 الدليل كامل بضغطة وحدة.', 'action' => 'افتح الرابط', 'result' => '✓ وصل الرابط خلال 0.4 ثانية',
                ]],
                ['title' => 'أطلق المسابقات', 'description' => 'اجمع المشاركات واختر الفائزين تلقائياً.', 'surface' => 'تيك توك · مسابقة', 'sr' => 'على تيك توك، يُؤكَّد كل تعليق في المسابقة كمشاركة ويُحتسب.', 'scene' => [
                    'handle' => 'hala.m', 'question' => 'أنا مشاركة! 🎉', 'reply' => 'تم تسجيلك يا هلا! نعلن الفائزين يوم الجمعة الساعة 8 مساءً.', 'action' => 'شاركي لمضاعفة فرصك',
                    'counter' => ['value' => '1,284', 'label' => 'مشاركة'],
                ]],
            ]],
            'try-it' => [
                'kicker' => 'بدون تسجيل', 'heading_lead' => 'لا تكتفِ بكلامنا.', 'heading_mark' => 'جرّبه.', 'lead' => 'اكتب تعليقاً مثل عملائك — وشاهد بلكس تشات يرد عليه.',
                'placeholder' => 'اكتب تعليقاً…', 'bridge' => 'هذا المسار أخذ 0 دقيقة لبنائه. مسارك يحتاج دقيقتين.', 'cta' => ['label' => 'ابنِ أول مسار لك'], 'handle' => 'أنت', 'initial' => 'أ',
                'suggestions' => array_values($comments),
            ],
            'get-started' => [
                'heading' => 'جاهز خلال دقائق، لا أسابيع.',
                'steps' => [
                    ['number' => '01', 'name' => 'سجّل مجاناً', 'line' => 'بدون بطاقة ائتمان، وبدون مكالمات.'],
                    ['number' => '02', 'name' => 'اربط قنواتك', 'line' => 'إنستغرام وواتساب وتيك توك وماسنجر — بضغطة لكل قناة.'],
                    ['number' => '03', 'name' => 'انطلق', 'line' => 'اختر مساراً، فعّله، وشاهد الردود تنطلق.'],
                ],
                'cta' => ['label' => 'ابدأ مجاناً'], 'secondary_cta' => ['label' => 'شاهد الخطط'],
            ],
            'numbers' => ['heading' => 'بلكس تشات بالأرقام'],
            'faq' => ['heading' => 'أسئلة وأجوبة.'],
            'navigation' => [
                'items' => [['label' => 'المنتج'], ['label' => 'الحلول'], ['label' => 'الوكالات'], ['label' => 'الأسعار']],
                'menus' => [
                    'product' => ['heading' => 'أتمتة لكل محادثة', 'items' => [
                        ['title' => 'Instagram', 'desc' => 'حوّل التعليقات والرسائل الخاصة إلى عملاء'],
                        ['title' => 'WhatsApp', 'desc' => 'رُدّ على كل محادثة، ليلًا ونهارًا'],
                        ['title' => 'Messenger', 'desc' => 'أتمتة صندوق الوارد لصفحتك على Facebook'],
                        ['title' => 'TikTok', 'desc' => 'حوّل التعليقات على فيديوهاتك إلى طلبات'],
                        ['title' => 'Blexchat AI', 'desc' => 'ردود تعرف كتالوج منتجاتك', 'cta' => 'جرّبه مباشرة'],
                    ]],
                    'solutions' => ['heading' => 'مصمَّم لطريقتك في البيع', 'items' => [
                        ['title' => 'صنّاع المحتوى', 'desc' => 'حوّل متابعيك إلى قائمة مشتركين.'],
                        ['title' => 'التجارة الإلكترونية', 'desc' => 'استرجع السلال المتروكة وأكّد الطلبات في الرسائل الخاصة.'],
                        ['title' => 'المطاعم', 'desc' => 'حجوزات وطلبات من الرسائل الخاصة.'],
                        ['title' => 'العيادات', 'desc' => 'مواعيد تُحجز وأنت منشغل بعملك.'],
                        ['title' => 'الوكالات', 'desc' => 'أدِر حسابات عملاء كثيرة بمستخدم واحد.'],
                        ['title' => 'العقارات', 'desc' => 'حدّد المشترين الجادّين قبل أول مكالمة.'],
                        ['title' => 'التعليم', 'desc' => 'سجّل الطلاب بدون صفحة هبوط.'],
                        ['title' => 'الدعم', 'desc' => 'أجب عن الأسئلة المتكررة تلقائيًا على مدار الساعة.'],
                    ]],
                ],
                'sign_in' => ['label' => 'تسجيل الدخول'],
                'get_started' => ['label' => 'ابدأ الآن'],
                'get_started_mobile' => ['label' => 'ابدأ مجانًا'],
                'aria' => ['main' => 'التنقل الرئيسي', 'mobile' => 'التنقل على الجوال', 'open_menu' => 'فتح القائمة', 'close_menu' => 'إغلاق القائمة'],
            ],
            'footer' => [
                'tagline' => 'حوّل التعليقات والرسائل الخاصة إلى عملاء.',
                'cta' => ['label' => 'ابدأ مجانًا'],
                'columns' => [
                    ['title' => 'المنتج', 'links' => [['label' => 'المزايا'], ['label' => 'الأسعار'], ['label' => 'Blexchat AI']]],
                    ['title' => 'الحلول', 'links' => [['label' => 'صنّاع المحتوى'], ['label' => 'التجارة الإلكترونية'], ['label' => 'المطاعم'], ['label' => 'الوكالات']]],
                    ['title' => 'الحساب', 'links' => [['label' => 'إنشاء حساب'], ['label' => 'تسجيل الدخول']]],
                ],
                'made_for' => 'صُمّم من أجل',
                'made_for_sr' => 'صُمّم من أجل صنّاع المحتوى والعلامات التجارية والبائعين والوكالات',
                'giant_words' => ['صنّاع المحتوى', 'العلامات التجارية', 'البائعين', 'الوكالات'],
                'copyright' => '© 2026 BLEXCHAT',
                'legal' => [['label' => 'الخصوصية'], ['label' => 'الشروط']],
                'aria' => ['footer' => 'تذييل الصفحة', 'home' => 'الصفحة الرئيسية لـ Blexchat'],
            ],
            'not_found' => [
                'question' => 'هل ما زالت هذه الصفحة موجودة؟',
                'replied' => 'تم الرد خلال 0.4 ث',
                'answer' => 'لا، لكن بقية الموقع موجودة. طريق العودة إلى الرئيسية في الأسفل.',
                'title' => 'هذه الصفحة غير موجودة.',
                'body' => 'قد يكون الرابط قديمًا، أو في العنوان خطأ إملائي.',
                'cta' => ['label' => 'العودة إلى الصفحة الرئيسية'],
            ],
            'demo_replies' => [
                'card' => ['label' => 'رد تلقائي', 'timer' => '0.4 ث'],
                'fallback' => ['reply' => 'وصلنا — نرسل لك التفاصيل الحين 💌', 'action' => 'شوف المنتج', 'result' => '✓ تم الرد خلال 0.4 ثانية'],
            ],
        ];
    }

    private function seedLists(): void
    {
        $t = fn (?array $pair): ?array => $pair === null ? null : ['en' => $pair[0], 'ar' => $pair[1]];

        foreach ([ComparisonPoint::class, HomeStat::class, FaqItem::class] as $model) {
            $model::query()->delete();
        }

        $useCases = [
            ['creators', 'creators', ['Creators', 'صنّاع المحتوى'], ['Turn engagement into income.', 'حوّل التفاعل إلى دخل.'], 'rounded', 'brand'],
            ['ecommerce', 'e-commerce', ['E-commerce', 'التجارة الإلكترونية'], ['Every comment is a checkout.', 'كل تعليق فرصة شراء.'], 'blob', 'tint'],
            ['restaurants', 'restaurants', ['Restaurants', 'المطاعم'], ['Bookings and orders from DMs.', 'حجوزات وطلبات من الرسائل.'], 'arch', 'ink'],
            ['clinics', 'clinics', ['Clinics', 'العيادات'], ['Appointments booked while you work.', 'مواعيد تُحجز وأنت تعمل.'], 'circle', 'tint'],
            ['agencies', 'agencies', ['Agencies', 'الوكالات'], ['Run automations for every client.', 'شغّل الأتمتة لكل عملائك.'], 'pill', 'brand'],
            ['realestate', 'real-estate', ['Real estate', 'العقارات'], ['Qualify leads before you call.', 'أهّل العملاء المحتملين قبل أن تتصل.'], 'house', 'ink'],
        ];

        foreach ($useCases as $sort => [$key, $slug, $name, $line, $shape, $tone]) {
            UseCase::updateOrCreate(['key' => $key], ['slug' => $slug, 'name' => $t($name), 'line' => $t($line), 'shape' => $shape, 'tone' => $tone, 'sort' => $sort + 1, 'active' => true]);
        }

        $comparison = [
            'before' => [
                ['Comments pile up unanswered', 'التعليقات تتراكم بدون رد'],
                ['DMs missed = sales missed', 'رسالة فائتة = بيعة فائتة'],
                ['Copy-pasting the same replies', 'نسخ ولصق نفس الردود'],
                ['Online 14 hours a day', 'متصل 14 ساعة في اليوم'],
            ],
            'after' => [
                ['Every comment answered in seconds', 'كل تعليق يُرد عليه في ثوانٍ'],
                ['DMs become orders while you sleep', 'الرسائل تتحول إلى طلبات وأنت نايم'],
                ['One flow replies on every channel', 'مسار واحد يرد على كل القنوات'],
                ['You check results, not messages', 'تتابع النتائج، لا الرسائل'],
            ],
        ];

        foreach ($comparison as $side => $points) {
            foreach ($points as $sort => $text) {
                ComparisonPoint::create(['side' => $side, 'text' => $t($text), 'sort' => $sort + 1]);
            }
        }

        $stats = [
            [['4', '4'], ['channels in one inbox', 'قنوات في صندوق وارد واحد']],
            [['24/7', '24/7'], ['automatic replies', 'ردود تلقائية']],
            [['AR · EN', 'عربي · إنجليزي'], ['Arabic & English', 'العربية والإنجليزية']],
            [['Seconds', 'ثوانٍ'], ['for every reply', 'لكل رد']],
        ];

        foreach ($stats as $sort => [$value, $label]) {
            HomeStat::create(['value' => $t($value), 'label' => $t($label), 'sort' => $sort + 1]);
        }

        $faq = [
            [['How much does it cost?', 'كم التكلفة؟'], ['There’s a free plan to start. Paid plans scale with your audience — see {link}.', 'فيه خطة مجانية للبداية، والخطط المدفوعة تكبر مع جمهورك — اطّلع على {link}.'], ['Pricing', 'صفحة الأسعار'], '/pricing'],
            [['Which channels are supported?', 'ما القنوات المدعومة؟'], ['Instagram, WhatsApp, TikTok and Messenger — one inbox, one set of automations.', 'إنستغرام وواتساب وتيك توك وماسنجر — صندوق واحد، ومجموعة أتمتة واحدة.'], null, null],
            [['Does it work in Arabic?', 'هل يدعم العربية؟'], ['Fully. Flows, replies and the dashboard all support Arabic and English.', 'بالكامل. المسارات والردود ولوحة التحكم كلها تدعم العربية والإنجليزية.'], null, null],
            [['Is it approved by Meta?', 'هل هو معتمد من Meta؟'], ['BLEXCHAT uses the official APIs.', 'بلكس تشات يستخدم الواجهات البرمجية الرسمية.'], null, null],
            [['How long does setup take?', 'كم يستغرق الإعداد؟'], ['Most businesses go live in under 10 minutes.', 'أغلب الأنشطة تنطلق في أقل من 10 دقائق.'], null, null],
            [['Is my data safe?', 'هل بياناتي آمنة؟'], ['Your conversations stay yours. We never sell data.', 'محادثاتك ملكك، ولا نبيع البيانات أبداً.'], null, null],
        ];

        foreach ($faq as $sort => [$question, $answer, $linkLabel, $linkUrl]) {
            FaqItem::create(array_filter(['question' => $t($question), 'answer' => $t($answer), 'link_label' => $t($linkLabel), 'link_url' => $linkUrl, 'sort' => $sort + 1]));
        }

        $rules = [
            ['price', ['how much', 'price', 'cost', 'بكم', 'كم سعر', 'السعر'], ['Hey! Just sent the price to your DMs 💌', 'هلا! أرسلنا لك السعر بالخاص 💌'], ['View product', 'شوف المنتج']],
            ['shipping', ['ship', 'deliver', 'delivery', 'riyadh', 'jeddah', 'ksa', 'توصيل', 'شحن', 'الرياض', 'جدة'], ['Yes! Free delivery across KSA 🚚', 'أكيد! التوصيل مجاني لكل مناطق المملكة 🚚'], ['Track order', 'تتبّع الطلب']],
            ['link', ['link', 'url', 'website', 'رابط', 'لينك', 'الموقع'], ['Sent! Check your DMs ✨', 'أرسلناه! شيك رسائلك ✨'], ['Open link', 'افتح الرابط']],
            ['stock', ['available', 'in stock', 'sold out', 'size', 'متوفر', 'موجود', 'مقاس'], ['Still in stock — I saved one for you 🙌', 'لسا متوفر — حجزت لك واحد 🙌'], ['View product', 'شوف المنتج']],
            ['booking', ['book', 'booking', 'appointment', 'reserve', 'table', 'حجز', 'موعد', 'احجز'], ['Done — pick a time and I will confirm it 📅', 'تم — اختر الوقت ونأكّد لك 📅'], ['Pick a time', 'اختر الوقت']],
        ];

        foreach ($rules as $sort => [$key, $keywords, $reply, $action]) {
            DemoReplyRule::updateOrCreate(['key' => $key], ['keywords' => $keywords, 'reply' => $t($reply), 'action' => $t($action), 'result' => $t(['✓ Replied in 0.4s', '✓ تم الرد خلال 0.4 ثانية']), 'sort' => $sort + 1, 'active' => true]);
        }
    }

    /**
     * Splits a block into the copy a translator edits and the values every language shares.
     *
     * @param  array<array-key, mixed>  $node
     * @return array{0: array<array-key, mixed>, 1: array<array-key, mixed>}
     */
    private function split(array $node): array
    {
        $texts = [];
        $settings = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                [$childTexts, $childSettings] = $this->split($value);

                if ($childTexts !== []) {
                    $texts[$key] = $childTexts;
                }

                if ($childSettings !== []) {
                    $settings[$key] = $childSettings;
                }

                continue;
            }

            $isCopy = is_string($value)
                && ! in_array($key, self::SHARED_KEYS, true)
                && ! str_starts_with($value, 'marketing/');

            if ($isCopy) {
                $texts[$key] = $value;
            } else {
                $settings[$key] = $value;
            }
        }

        return [$texts, $settings];
    }
}
