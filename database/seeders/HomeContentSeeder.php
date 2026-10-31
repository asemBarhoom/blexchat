<?php

namespace Database\Seeders;

use App\Models\ComparisonPoint;
use App\Models\Creator;
use App\Models\DemoReplyRule;
use App\Models\FaqItem;
use App\Models\FooterLink;
use App\Models\HomeBlock;
use App\Models\HomeStat;
use App\Models\NavMenuItem;
use App\Models\UseCase;
use Illuminate\Database\Seeder;

/**
 * Resets the marketing homepage to its default English content. Running it replaces
 * whatever was edited in the admin panel, so it is meant for fresh installs and tests.
 */
class HomeContentSeeder extends Seeder
{
    /** Keys whose string values are the same in every language (ids, routes, frontend switches). */
    private const SHARED_KEYS = ['id', 'type', 'pose', 'channel', 'shape', 'tone', 'loop', 'slug', 'target', 'url', 'menu', 'code', 'group', 'replies_from'];

    public function run(): void
    {
        $this->seedBlocks();
        $this->seedLists();
    }

    private function seedBlocks(): void
    {
        $image = fn (string $path): string => 'marketing/home/'.$path;

        $blocks = [
            'navigation' => [
                'brand' => 'Blexchat',
                'labels' => ['home' => 'Blexchat home', 'main' => 'Main', 'mobile' => 'Mobile', 'open_menu' => 'Open menu', 'close_menu' => 'Close menu', 'language' => 'Language', 'language_button' => 'Change language, current language English'],
                'items' => [
                    ['label' => 'Product', 'menu' => 'product'],
                    ['label' => 'Solutions', 'menu' => 'solutions'],
                    ['label' => 'Agencies', 'url' => '/solutions/agencies'],
                    ['label' => 'Pricing', 'url' => '/pricing'],
                ],
                'account' => [
                    ['label' => 'Sign in', 'url' => '/signin'],
                    ['label' => 'Get started', 'url' => '/signup'],
                ],
                'languages' => [['code' => 'en', 'label' => 'EN'], ['code' => 'ar', 'label' => 'العربية']],
                'menus' => [
                    'product' => ['heading' => 'Automate every conversation'],
                    'solutions' => ['heading' => 'Built for how you sell'],
                ],
            ],
            'hero' => [
                'id' => 'hero', 'type' => 'hero',
                'headline_rotations' => [['Reach', 'Millions'], ['Sell in', 'every DM'], ['Reply to', 'everyone']],
                'handwriting' => 'automatically.',
                'photos' => ['relax' => $image('hero-photo.webp'), 'celebrate' => $image('hero-photo-celebrate.webp')],
                'story' => [
                    'scenes' => [['id' => 'pressure', 'pose' => 'pressure', 'seconds' => 2.2], ['id' => 'working', 'pose' => 'relax', 'seconds' => 4.6], ['id' => 'relief', 'pose' => 'celebrate', 'seconds' => 2.4]],
                    'pressure' => ['counter' => 'new messages', 'count' => 47, 'more' => '+44 more', 'chips' => [
                        ['id' => 'c1', 'handle' => 'sara.styles', 'text' => 'How much is this? 😍'],
                        ['id' => 'c2', 'handle' => 'omar.fit', 'text' => 'Do you ship to Riyadh?'],
                        ['id' => 'c3', 'handle' => 'lina.m', 'text' => 'LINK PLEASE 🙏'],
                    ]],
                    'working_badge' => 'BLEXCHAT is replying',
                    'relief' => ['stats' => [['id' => 'replies', 'to' => 47, 'suffix' => '', 'label' => 'replies'], ['id' => 'orders', 'to' => 12, 'suffix' => '', 'label' => 'orders'], ['id' => 'saved', 'to' => 3, 'suffix' => 'h', 'label' => 'saved']], 'illustrative' => true],
                    'visitor_result' => 'Replied in 0.4s',
                ],
                'composer' => ['label' => 'Try it live', 'placeholder_prefix' => 'Write a comment…', 'placeholder' => 'Write a comment… e.g. How much?', 'sr_label' => 'Try a comment and see the automatic reply', 'send_label' => 'Send comment', 'examples' => ['How much?', 'Do you ship to Riyadh?', 'Link please 🙏']],
                'subtitle' => 'BLEXCHAT turns comments and DMs into customers.',
                'description' => 'Smart automations that sell and reply for you, 24/7.',
                'cta' => ['label' => 'Get started free', 'url' => '/signup'],
                'microcopy' => 'Free plan · No credit card required',
                'photo' => $image('hero-photo.webp'),
                'cutout' => $image('hero-person-upper.webp'),
                'social_proof' => ['rating' => '4.8/5', 'count' => '2,000+ businesses', 'illustrative' => true, 'avatars' => [$image('avatars/sara.webp'), $image('avatars/omar.webp'), $image('avatars/lina.webp'), $image('avatars/khalid.webp')]],
                'channels_label' => 'Works with',
                'channels' => ['Instagram', 'WhatsApp', 'TikTok', 'Messenger'],
                'partners' => ['Meta Business Partner', 'TikTok Marketing Partner'],
                'partner_claims_unverified' => true,
                'conversations' => [
                    ['id' => 'sara', 'channel' => 'instagram', 'handle' => 'sara.styles', 'initial' => 'S', 'avatar' => $image('avatars/sara.webp'), 'question' => 'How much is this? 😍', 'reply' => 'Hey Sara! Just sent the price to your DMs 💌', 'action' => 'View product'],
                    ['id' => 'omar', 'channel' => 'whatsapp', 'handle' => 'omar.fit', 'initial' => 'O', 'avatar' => $image('avatars/omar.webp'), 'question' => 'Do you ship to Riyadh?', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order'],
                    ['id' => 'lina', 'channel' => 'tiktok', 'handle' => 'lina.m', 'initial' => 'L', 'avatar' => $image('avatars/lina.webp'), 'question' => 'LINK PLEASE 🙏', 'reply' => 'Sent! Check your DMs ✨', 'action' => 'Open link', 'result' => '✓ New order · $49'],
                ],
            ],
            'creators' => ['id' => 'creators', 'type' => 'creator_strip', 'label' => 'Loved by 1M+ creators, marketers & brands', 'illustrative' => true],
            'intro' => ['id' => 'intro', 'type' => 'intro', 'headline' => ['Scale up your best', 'conversations'], 'lead' => 'Powerful automations for every way you sell, reply and grow.'],
            'features' => ['id' => 'features', 'type' => 'vertical_slider', 'total' => 4, 'slides_label' => 'Feature slides', 'slides' => [
                ['id' => 'comments', 'index' => '01', 'headline' => 'Turn comments into customers', 'body' => '"How much is this?" → instant reply, and the sale happens while you sleep.', 'cta' => 'See how', 'target' => '#see-it-in-action?tab=comment', 'phone_label' => 'blexchat.store', 'channel' => 'instagram', 'scene' => ['post' => 'New drop · 2h', 'post_photo' => $image('scenes/nora.webp'), 'comments' => [['handle' => 'sara.styles', 'text' => 'How much is this? 😍'], ['handle' => 'noura.k', 'text' => 'Is it still available?'], ['handle' => 'omar.fit', 'text' => 'Link please 🙏']], 'reply' => 'Sent you the price and the link in DMs 💌', 'action' => 'View product', 'order' => 'New order · $49', 'illustrative' => true]],
                ['id' => 'dms', 'index' => '02', 'headline' => 'Never leave a DM unanswered', 'body' => 'Smart replies handle FAQs, capture phone and email, and hand over to you only when it matters.', 'cta' => 'See how', 'target' => '#see-it-in-action?tab=faq', 'phone_label' => 'Direct', 'channel' => 'whatsapp', 'scene' => ['handle' => 'omar.fit', 'avatar' => $image('avatars/omar.webp'), 'question' => 'Do you ship to Riyadh?', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order', 'seen' => 'Seen', 'lead' => ['name' => 'Omar A.', 'detail' => '+966 5• ••• 4412 · omar@mail.com', 'tag' => 'Lead captured'], 'illustrative' => true]],
                ['id' => 'stories', 'index' => '03', 'headline' => 'Reply to every story mention', 'body' => 'Turn reactions into conversations automatically, 24/7.', 'cta' => 'See how', 'target' => '#try-it', 'phone_label' => 'Your story', 'channel' => 'instagram', 'scene' => ['story' => 'Reacted to your story', 'photo' => $image('scenes/lina.webp'), 'handle' => 'lina.m', 'reaction' => '❤️', 'reply' => 'Thank you Lina! Here is the link ✨', 'action' => 'Open link']],
                ['id' => 'channels', 'index' => '04', 'headline' => 'Grow across every channel', 'body' => 'Instagram, WhatsApp, TikTok, Messenger — one inbox, one set of automations.', 'cta' => 'See how', 'target' => '/signup', 'phone_label' => 'All inboxes', 'channel' => 'messenger', 'scene' => ['inbox' => 'One inbox', 'total' => '128', 'total_label' => 'conversations today', 'tiles' => [['id' => 'instagram', 'count' => '64'], ['id' => 'whatsapp', 'count' => '38'], ['id' => 'tiktok', 'count' => '19'], ['id' => 'messenger', 'count' => '7']], 'footer' => 'All answered automatically', 'illustrative' => true]],
            ]],
            'autopilot' => ['id' => 'autopilot', 'type' => 'giant_word', 'kicker' => 'This is what we call', 'word' => ['Auto', 'pilot'], 'mark' => '24/7', 'photo' => $image('hero-photo-celebrate.webp')],
            'use-cases' => ['id' => 'use-cases', 'type' => 'use_cases', 'heading' => 'Built for the way YOU sell', 'cta' => 'See how', 'prev_label' => 'Previous use cases', 'next_label' => 'Next use cases'],
            'before-after' => ['id' => 'before-after', 'type' => 'comparison', 'heading' => 'Your inbox: a before & after', 'before' => ['title' => 'Before BLEXCHAT — all work, no play'], 'after' => ['title' => 'After BLEXCHAT — less grind, more pay'], 'cta' => ['label' => 'Get started free', 'url' => '/signup']],
            'see-it-in-action' => ['id' => 'see-it-in-action', 'type' => 'flow_tabs', 'heading' => 'See it in action', 'lead' => 'Pick a flow — watch BLEXCHAT run it.', 'composer_placeholder' => 'Message…', 'tabs_label' => 'Flows', 'tabs' => [
                ['id' => 'follow', 'title' => 'Say hi to new followers', 'description' => 'A warm DM the moment they follow.', 'surface' => 'Instagram · Direct', 'sr' => 'On Instagram, a new follower gets a welcome DM straight away and joins the drop list.', 'scene' => ['event' => 'sara.styles started following you', 'avatar' => $image('avatars/sara.webp'), 'reply' => "Hey Sara 👋 thanks for the follow! Want first dibs on Thursday's drop?", 'action' => 'Yes, add me', 'result' => '✓ New subscriber']],
                ['id' => 'welcome', 'title' => 'Welcome messages', 'description' => 'Greet every first message instantly.', 'surface' => 'WhatsApp · Business', 'sr' => 'On WhatsApp, a first message is greeted instantly and the contact is saved as a lead.', 'scene' => ['handle' => 'Omar', 'question' => 'Salam! Are you open today?', 'avatar' => $image('avatars/omar.webp'), 'reply' => 'Wa alaikum salam Omar! Open 10am–11pm. What can we get you?', 'action' => 'See the menu', 'lead' => ['tag' => 'New lead saved', 'name' => 'Omar K.', 'detail' => '+971 5X XXX XXXX · WhatsApp'], 'illustrative' => true]],
                ['id' => 'faq', 'title' => 'Automate FAQs', 'description' => 'Price, delivery, sizes — answered in seconds.', 'surface' => 'Instagram · Story replies', 'sr' => 'A story reply asking the price is answered in seconds and turns into an order.', 'scene' => ['event' => 'Replied to your story', 'handle' => 'lina.k', 'question' => 'How much is this? 😍', 'avatar' => $image('avatars/lina.webp'), 'reply' => "It's 180 AED — sizes S to XL, delivered in 2 days.", 'action' => 'Order now', 'result' => '✓ Order #1042 placed', 'illustrative' => true]],
                ['id' => 'comment', 'title' => 'Auto-DM from comments', 'description' => 'Comment ‘LINK’ → the link lands in DMs.', 'surface' => 'Instagram · Comments → DM', 'sr' => 'Someone comments LINK under a post and the link lands in their DMs.', 'scene' => ['handle' => 'khalid.r', 'question' => 'LINK', 'avatar' => $image('avatars/khalid.webp'), 'transition' => '↓ sent to DMs', 'reply' => 'Here you go, Khalid 🔗 the full guide is one tap away.', 'action' => 'Open the link', 'result' => '✓ Link delivered in 0.4s']],
                ['id' => 'giveaway', 'title' => 'Run giveaways', 'description' => 'Collect entries and pick winners automatically.', 'surface' => 'TikTok · Giveaway', 'sr' => 'On TikTok, every giveaway comment is confirmed as an entry and counted.', 'scene' => ['handle' => 'nora.m', 'question' => 'Count me in! 🎉', 'reply' => "You're in, Nora! Winners are picked Friday at 8pm.", 'action' => 'Share for 2× entries', 'counter' => ['channel' => 'tiktok', 'value' => '1,284', 'label' => 'Entries collected'], 'illustrative' => true]],
            ]],
            'try-it' => ['id' => 'try-it', 'type' => 'live_demo', 'kicker' => 'No signup needed', 'heading_lead' => "Don't take our word for it.", 'heading_mark' => 'Try it.', 'lead' => 'Write a comment like your customers do — watch BLEXCHAT answer it.', 'placeholder' => 'Write a comment…', 'bridge' => 'That flow took 0 minutes to build. Yours takes 2.', 'cta' => ['label' => 'Build your first flow', 'url' => '/signup'], 'handle' => 'you', 'initial' => 'Y', 'suggestions' => ['How much is this? 😍', 'Do you ship to Riyadh?', 'Link please 🙏'], 'replies_from' => 'demo_replies'],
            'get-started' => ['id' => 'get-started', 'type' => 'steps', 'heading' => 'Live in minutes, not weeks.', 'steps' => [['number' => '01', 'name' => 'Sign up free', 'line' => 'No credit card, no calls.', 'loop' => 'tick'], ['number' => '02', 'name' => 'Connect your channels', 'line' => 'Instagram, WhatsApp, TikTok, Messenger — one tap each.', 'loop' => 'channels'], ['number' => '03', 'name' => 'Go live', 'line' => 'Pick a flow, switch it on, watch replies happen.', 'loop' => 'switch']], 'cta' => ['label' => 'Get started free', 'url' => '/signup'], 'secondary_cta' => ['label' => 'See plans', 'url' => '/pricing']],
            'numbers' => ['id' => 'numbers', 'type' => 'numbers', 'heading' => 'BLEXCHAT in numbers', 'illustrative' => true],
            'faq' => ['id' => 'faq', 'type' => 'faq', 'heading' => 'Questions, answered.'],
            'demo_replies' => [
                'card' => ['label' => 'Auto-reply', 'timer' => '0.4s'],
                'note' => 'Shared by the hero composer and the try-it section. Match keywords case-insensitively as substrings; first matching rule wins.',
                'fallback' => ['id' => 'fallback', 'reply' => 'Got it — sending you the details right now 💌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'],
            ],
            'footer' => [
                'brand' => 'Blexchat', 'home_label' => 'Blexchat home', 'nav_label' => 'Footer', 'giant_label' => 'Made for creators, brands, sellers and agencies', 'tagline' => 'Turn comments and DMs into customers.',
                'columns' => [['group' => 'product', 'title' => 'Product'], ['group' => 'solutions', 'title' => 'Solutions'], ['group' => 'account', 'title' => 'Account']],
                'giant_lead' => 'Made for', 'giant_words' => ['Creators', 'Brands', 'Sellers', 'Agencies'], 'cta' => ['label' => 'Get started free', 'url' => '/signup'], 'copyright' => '© 2026 BLEXCHAT', 'languages' => ['EN', 'العربية'],
            ],
        ];

        $sections = ['hero', 'creators', 'intro', 'features', 'autopilot', 'use-cases', 'before-after', 'see-it-in-action', 'try-it', 'get-started', 'numbers', 'faq'];

        foreach ($blocks as $key => $content) {
            [$texts, $settings] = $this->split($content);
            $sort = array_search($key, $sections, true);

            HomeBlock::updateOrCreate(['key' => $key], [
                'sort' => $sort === false ? null : $sort + 1,
                'texts' => ['en' => $texts],
                'settings' => $settings,
            ]);
        }
    }

    private function seedLists(): void
    {
        $image = fn (string $path): string => 'marketing/home/'.$path;
        $en = fn (?string $text): ?array => $text === null ? null : ['en' => $text];

        foreach ([NavMenuItem::class, FooterLink::class, ComparisonPoint::class, HomeStat::class, FaqItem::class] as $model) {
            $model::query()->delete();
        }

        $menus = [
            'product' => [
                ['Instagram', 'Turn comments and DMs into customers'],
                ['WhatsApp', 'Answer every chat, day and night'],
                ['Messenger', 'Automate your Facebook Page inbox'],
                ['TikTok', 'Turn comments on your videos into orders'],
                ['Blexchat AI', 'Replies that know your catalogue', 'Try it live', '#try-it'],
            ],
            'solutions' => [
                ['Creators', 'Turn followers into a subscriber list.'],
                ['E-commerce', 'Recover carts and confirm orders in DM.'],
                ['Restaurants', 'Bookings and orders from DMs.'],
                ['Clinics', 'Appointments booked while you work.'],
                ['Agencies', 'Run many client accounts from one seat.'],
                ['Real estate', 'Qualify buyers before the first call.'],
                ['Education', 'Enrol students without a landing page.'],
                ['Support', 'Deflect repeat questions around the clock.'],
            ],
        ];

        foreach ($menus as $menu => $items) {
            foreach ($items as $sort => $item) {
                NavMenuItem::create(array_filter(['menu' => $menu, 'title' => $en($item[0]), 'description' => $en($item[1]), 'cta' => $en($item[2] ?? null), 'target' => $item[3] ?? null, 'sort' => $sort + 1]));
            }
        }

        $creators = [
            ['nora', 'Nora Al-Qahtani', '@noraqtn', '184K followers', 'I stopped losing sales in my DMs.'],
            ['omar', 'Omar Bin Saleh', '@omar.fit', '92K followers', 'Every comment gets an answer now.'],
            ['lina', 'Lina Mansour', '@lina.m', '310K followers', 'It replies while I sleep.'],
            ['faisal', 'Faisal Otaibi', '@faisalotb', '47K followers', 'Set up in a morning, paid for itself by Friday.'],
            ['huda', 'Huda Kamal', '@hudamakes', '128K followers', 'My inbox went from scary to sorted.'],
        ];

        foreach ($creators as $sort => [$key, $name, $handle, $followers, $quote]) {
            Creator::updateOrCreate(['key' => $key], ['name' => $en($name), 'handle' => $handle, 'followers' => $en($followers), 'quote' => $en($quote), 'photo' => $image("creators/{$key}.webp"), 'sort' => $sort + 1, 'active' => true]);
        }

        $useCases = [
            ['creators', 'creators', 'Creators', 'Turn engagement into income.', 'rounded', 'brand'],
            ['ecommerce', 'e-commerce', 'E-commerce', 'Every comment is a checkout.', 'blob', 'tint'],
            ['restaurants', 'restaurants', 'Restaurants', 'Bookings and orders from DMs.', 'arch', 'ink'],
            ['clinics', 'clinics', 'Clinics', 'Appointments booked while you work.', 'circle', 'tint'],
            ['agencies', 'agencies', 'Agencies', 'Run automations for every client.', 'pill', 'brand'],
            ['realestate', 'real-estate', 'Real estate', 'Qualify leads before you call.', 'house', 'ink'],
        ];

        foreach ($useCases as $sort => [$key, $slug, $name, $line, $shape, $tone]) {
            UseCase::updateOrCreate(['key' => $key], ['slug' => $slug, 'name' => $en($name), 'line' => $en($line), 'shape' => $shape, 'tone' => $tone, 'sort' => $sort + 1, 'active' => true]);
        }

        $comparison = [
            'before' => ['Comments pile up unanswered', 'DMs missed = sales missed', 'Copy-pasting the same replies', 'Online 14 hours a day'],
            'after' => ['Every comment answered in seconds', 'DMs become orders while you sleep', 'One flow replies on every channel', 'You check results, not messages'],
        ];

        foreach ($comparison as $side => $points) {
            foreach ($points as $sort => $text) {
                ComparisonPoint::create(['side' => $side, 'text' => $en($text), 'sort' => $sort + 1]);
            }
        }

        $stats = [['1.2M+', 'Messages automated'], ['0.4s', 'Average reply time'], ['$2M+', 'Revenue recovered'], ['2,000+', 'Businesses']];

        foreach ($stats as $sort => [$value, $label]) {
            HomeStat::create(['value' => $en($value), 'label' => $en($label), 'sort' => $sort + 1]);
        }

        $faq = [
            ['How much does it cost?', 'There’s a free plan to start. Paid plans scale with your audience — see Pricing.', 'Pricing', '/pricing', false],
            ['Which channels are supported?', 'Instagram, WhatsApp, TikTok and Messenger — one inbox, one set of automations.', null, null, false],
            ['Does it work in Arabic?', 'Fully. Flows, replies and the dashboard all support Arabic and English.', null, null, false],
            ['Is it approved by Meta?', 'BLEXCHAT uses the official APIs.', null, null, true],
            ['How long does setup take?', 'Most businesses go live in under 10 minutes.', null, null, true],
            ['Is my data safe?', 'Your conversations stay yours. We never sell data.', null, null, true],
        ];

        foreach ($faq as $sort => [$question, $answer, $linkLabel, $linkUrl, $unverified]) {
            FaqItem::create(array_filter(['question' => $en($question), 'answer' => $en($answer), 'link_label' => $en($linkLabel), 'link_url' => $linkUrl, 'sort' => $sort + 1]) + ['claim_unverified' => $unverified]);
        }

        $rules = [
            ['price', ['how much', 'price', 'cost', 'بكم', 'كم سعر', 'السعر'], 'Hey! Just sent the price to your DMs 💌', 'View product'],
            ['shipping', ['ship', 'deliver', 'delivery', 'riyadh', 'jeddah', 'ksa', 'توصيل', 'شحن', 'الرياض', 'جدة'], 'Yes! Free delivery across KSA 🚚', 'Track order'],
            ['link', ['link', 'url', 'website', 'رابط', 'لينك', 'الموقع'], 'Sent! Check your DMs ✨', 'Open link'],
            ['stock', ['available', 'in stock', 'sold out', 'size', 'متوفر', 'موجود', 'مقاس'], 'Still in stock — I saved one for you 🙌', 'View product'],
            ['booking', ['book', 'booking', 'appointment', 'reserve', 'table', 'حجز', 'موعد', 'احجز'], 'Done — pick a time and I will confirm it 📅', 'Pick a time'],
        ];

        foreach ($rules as $sort => [$key, $keywords, $reply, $action]) {
            DemoReplyRule::updateOrCreate(['key' => $key], ['keywords' => $keywords, 'reply' => $en($reply), 'action' => $en($action), 'result' => $en('✓ Replied in 0.4s'), 'sort' => $sort + 1, 'active' => true]);
        }

        $footer = [
            'product' => [['Features', '#features'], ['Pricing', '/pricing'], ['Blexchat AI', '#try-it']],
            'solutions' => [['Creators', '/solutions/creators'], ['E-commerce', '/solutions/e-commerce'], ['Restaurants', '/solutions/restaurants'], ['Agencies', '/solutions/agencies']],
            'account' => [['Sign up', '/signup'], ['Sign in', '/signin']],
            'legal' => [['Privacy', '/privacy'], ['Terms', '/terms']],
        ];

        foreach ($footer as $group => $links) {
            foreach ($links as $sort => [$label, $url]) {
                FooterLink::create(['group' => $group, 'label' => $en($label), 'url' => $url, 'sort' => $sort + 1]);
            }
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
