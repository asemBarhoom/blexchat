<?php

namespace Database\Seeders;

use App\Models\MarketingPage;
use Illuminate\Database\Seeder;

class MarketingHomeSeeder extends Seeder
{
    public function run(): void
    {
        $image = fn (string $path): string => '/storage/marketing/home/'.$path;

        MarketingPage::updateOrCreate(
            ['slug' => 'home', 'locale' => 'en'],
            ['content' => [
                'navigation' => [
                    'brand' => 'Blexchat',
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
                        'product' => ['heading' => 'Automate every conversation', 'items' => [
                            ['title' => 'Instagram', 'description' => 'Turn comments and DMs into customers'],
                            ['title' => 'WhatsApp', 'description' => 'Answer every chat, day and night'],
                            ['title' => 'Messenger', 'description' => 'Automate your Facebook Page inbox'],
                            ['title' => 'TikTok', 'description' => 'Turn comments on your videos into orders'],
                            ['title' => 'Blexchat AI', 'description' => 'Replies that know your catalogue', 'cta' => 'Try it live', 'target' => '#try-it'],
                        ]],
                        'solutions' => ['heading' => 'Built for how you sell', 'items' => [
                            ['title' => 'Creators', 'description' => 'Turn followers into a subscriber list.'],
                            ['title' => 'E-commerce', 'description' => 'Recover carts and confirm orders in DM.'],
                            ['title' => 'Restaurants', 'description' => 'Bookings and orders from DMs.'],
                            ['title' => 'Clinics', 'description' => 'Appointments booked while you work.'],
                            ['title' => 'Agencies', 'description' => 'Run many client accounts from one seat.'],
                            ['title' => 'Real estate', 'description' => 'Qualify buyers before the first call.'],
                            ['title' => 'Education', 'description' => 'Enrol students without a landing page.'],
                            ['title' => 'Support', 'description' => 'Deflect repeat questions around the clock.'],
                        ]],
                    ],
                ],
                'sections' => [
                    ['id' => 'hero', 'type' => 'hero', 'headline_rotations' => [['Reach', 'Millions'], ['Sell in', 'every DM'], ['Reply to', 'everyone']], 'handwriting' => 'automatically.',
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
                        'subtitle' => 'BLEXCHAT turns comments and DMs into customers.', 'description' => 'Smart automations that sell and reply for you, 24/7.', 'cta' => ['label' => 'Get started free', 'url' => '/signup'], 'microcopy' => 'Free plan · No credit card required', 'photo' => $image('hero-photo.webp'), 'cutout' => $image('hero-person-upper.webp'), 'composer' => ['label' => 'Try it live', 'placeholder' => 'Write a comment… e.g. How much?', 'send_label' => 'Send comment'], 'social_proof' => ['rating' => '4.8/5', 'count' => '2,000+ businesses', 'illustrative' => true, 'avatars' => [$image('avatars/sara.webp'), $image('avatars/omar.webp'), $image('avatars/lina.webp'), $image('avatars/khalid.webp')]], 'channels_label' => 'Works with', 'channels' => ['Instagram', 'WhatsApp', 'TikTok', 'Messenger'], 'partners' => ['Meta Business Partner', 'TikTok Marketing Partner'], 'partner_claims_unverified' => true, 'conversations' => [
                        ['id' => 'sara', 'channel' => 'instagram', 'handle' => 'sara.styles', 'initial' => 'S', 'avatar' => $image('avatars/sara.webp'), 'question' => 'How much is this? 😍', 'reply' => 'Hey Sara! Just sent the price to your DMs 💌', 'action' => 'View product'],
                        ['id' => 'omar', 'channel' => 'whatsapp', 'handle' => 'omar.fit', 'initial' => 'O', 'avatar' => $image('avatars/omar.webp'), 'question' => 'Do you ship to Riyadh?', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order'],
                        ['id' => 'lina', 'channel' => 'tiktok', 'handle' => 'lina.m', 'initial' => 'L', 'avatar' => $image('avatars/lina.webp'), 'question' => 'LINK PLEASE 🙏', 'reply' => 'Sent! Check your DMs ✨', 'action' => 'Open link', 'result' => '✓ New order · $49'],
                    ]],
                    ['id' => 'creators', 'type' => 'creator_strip', 'label' => 'Loved by 1M+ creators, marketers & brands', 'illustrative' => true, 'items' => [
                        ['id' => 'nora', 'name' => 'Nora Al-Qahtani', 'handle' => '@noraqtn', 'followers' => '184K followers', 'quote' => 'I stopped losing sales in my DMs.', 'photo' => $image('creators/nora.webp')],
                        ['id' => 'omar', 'name' => 'Omar Bin Saleh', 'handle' => '@omar.fit', 'followers' => '92K followers', 'quote' => 'Every comment gets an answer now.', 'photo' => $image('creators/omar.webp')],
                        ['id' => 'lina', 'name' => 'Lina Mansour', 'handle' => '@lina.m', 'followers' => '310K followers', 'quote' => 'It replies while I sleep.', 'photo' => $image('creators/lina.webp')],
                        ['id' => 'faisal', 'name' => 'Faisal Otaibi', 'handle' => '@faisalotb', 'followers' => '47K followers', 'quote' => 'Set up in a morning, paid for itself by Friday.', 'photo' => $image('creators/faisal.webp')],
                        ['id' => 'huda', 'name' => 'Huda Kamal', 'handle' => '@hudamakes', 'followers' => '128K followers', 'quote' => 'My inbox went from scary to sorted.', 'photo' => $image('creators/huda.webp')],
                    ]],
                    ['id' => 'intro', 'type' => 'intro', 'headline' => ['Scale up your best', 'conversations'], 'lead' => 'Powerful automations for every way you sell, reply and grow.'],
                    ['id' => 'features', 'type' => 'vertical_slider', 'total' => 4, 'slides' => [
                        ['id' => 'comments', 'index' => '01', 'headline' => 'Turn comments into customers', 'body' => '"How much is this?" → instant reply, and the sale happens while you sleep.', 'cta' => 'See how', 'target' => '#see-it-in-action?tab=comment', 'phone_label' => 'blexchat.store', 'channel' => 'instagram', 'scene' => ['post' => 'New drop · 2h', 'post_photo' => $image('scenes/nora.webp'), 'comments' => [['handle' => 'sara.styles', 'text' => 'How much is this? 😍'], ['handle' => 'noura.k', 'text' => 'Is it still available?'], ['handle' => 'omar.fit', 'text' => 'Link please 🙏']], 'reply' => 'Sent you the price and the link in DMs 💌', 'action' => 'View product', 'order' => 'New order · $49']],
                        ['id' => 'dms', 'index' => '02', 'headline' => 'Never leave a DM unanswered', 'body' => 'Smart replies handle FAQs, capture phone and email, and hand over to you only when it matters.', 'cta' => 'See how', 'target' => '#see-it-in-action?tab=faq', 'phone_label' => 'Direct', 'channel' => 'whatsapp', 'scene' => ['handle' => 'omar.fit', 'avatar' => $image('avatars/omar.webp'), 'question' => 'Do you ship to Riyadh?', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order', 'seen' => 'Seen', 'lead' => ['name' => 'Omar A.', 'detail' => '+966 5• ••• 4412 · omar@mail.com', 'tag' => 'Lead captured']]],
                        ['id' => 'stories', 'index' => '03', 'headline' => 'Reply to every story mention', 'body' => 'Turn reactions into conversations automatically, 24/7.', 'cta' => 'See how', 'target' => '#try-it', 'phone_label' => 'Your story', 'channel' => 'instagram', 'scene' => ['story' => 'Reacted to your story', 'photo' => $image('scenes/lina.webp'), 'handle' => 'lina.m', 'reaction' => '❤️', 'reply' => 'Thank you Lina! Here is the link ✨', 'action' => 'Open link']],
                        ['id' => 'channels', 'index' => '04', 'headline' => 'Grow across every channel', 'body' => 'Instagram, WhatsApp, TikTok, Messenger — one inbox, one set of automations.', 'cta' => 'See how', 'target' => '/signup', 'phone_label' => 'All inboxes', 'channel' => 'messenger', 'scene' => ['inbox' => 'One inbox', 'total' => '128', 'total_label' => 'conversations today', 'tiles' => [['id' => 'instagram', 'count' => '64'], ['id' => 'whatsapp', 'count' => '38'], ['id' => 'tiktok', 'count' => '19'], ['id' => 'messenger', 'count' => '7']], 'footer' => 'All answered automatically', 'illustrative' => true]],
                    ]],
                    ['id' => 'autopilot', 'type' => 'giant_word', 'kicker' => 'This is what we call', 'word' => ['Auto', 'pilot'], 'mark' => '24/7', 'photo' => $image('hero-photo-celebrate.webp')],
                    ['id' => 'use-cases', 'type' => 'use_cases', 'heading' => 'Built for the way YOU sell', 'cta' => 'See how', 'items' => [
                        ['id' => 'creators', 'slug' => 'creators', 'name' => 'Creators', 'line' => 'Turn engagement into income.', 'shape' => 'rounded', 'tone' => 'brand'],
                        ['id' => 'ecommerce', 'slug' => 'e-commerce', 'name' => 'E-commerce', 'line' => 'Every comment is a checkout.', 'shape' => 'blob', 'tone' => 'tint'],
                        ['id' => 'restaurants', 'slug' => 'restaurants', 'name' => 'Restaurants', 'line' => 'Bookings and orders from DMs.', 'shape' => 'arch', 'tone' => 'ink'],
                        ['id' => 'clinics', 'slug' => 'clinics', 'name' => 'Clinics', 'line' => 'Appointments booked while you work.', 'shape' => 'circle', 'tone' => 'tint'],
                        ['id' => 'agencies', 'slug' => 'agencies', 'name' => 'Agencies', 'line' => 'Run automations for every client.', 'shape' => 'pill', 'tone' => 'brand'],
                        ['id' => 'realestate', 'slug' => 'real-estate', 'name' => 'Real estate', 'line' => 'Qualify leads before you call.', 'shape' => 'house', 'tone' => 'ink'],
                    ]],
                    ['id' => 'before-after', 'type' => 'comparison', 'heading' => 'Your inbox: a before & after', 'before' => ['title' => 'Before BLEXCHAT — all work, no play', 'rows' => ['Comments pile up unanswered', 'DMs missed = sales missed', 'Copy-pasting the same replies', 'Online 14 hours a day']], 'after' => ['title' => 'After BLEXCHAT — less grind, more pay', 'rows' => ['Every comment answered in seconds', 'DMs become orders while you sleep', 'One flow replies on every channel', 'You check results, not messages']], 'cta' => ['label' => 'Get started free', 'url' => '/signup']],
                    ['id' => 'see-it-in-action', 'type' => 'flow_tabs', 'heading' => 'See it in action', 'lead' => 'Pick a flow — watch BLEXCHAT run it.', 'composer_placeholder' => 'Message…', 'tabs' => [
                        ['id' => 'follow', 'title' => 'Say hi to new followers', 'description' => 'A warm DM the moment they follow.', 'surface' => 'Instagram · Direct', 'scene' => ['event' => 'sara.styles started following you', 'avatar' => $image('avatars/sara.webp'), 'reply' => "Hey Sara 👋 thanks for the follow! Want first dibs on Thursday's drop?", 'action' => 'Yes, add me', 'result' => '✓ New subscriber']],
                        ['id' => 'welcome', 'title' => 'Welcome messages', 'description' => 'Greet every first message instantly.', 'surface' => 'WhatsApp · Business', 'scene' => ['handle' => 'Omar', 'question' => 'Salam! Are you open today?', 'avatar' => $image('avatars/omar.webp'), 'reply' => 'Wa alaikum salam Omar! Open 10am–11pm. What can we get you?', 'action' => 'See the menu', 'result' => 'New lead saved', 'contact' => 'Omar K. · +971 5X XXX XXXX · WhatsApp']],
                        ['id' => 'faq', 'title' => 'Automate FAQs', 'description' => 'Price, delivery, sizes — answered in seconds.', 'surface' => 'Instagram · Story replies', 'scene' => ['event' => 'Replied to your story', 'handle' => 'lina.k', 'question' => 'How much is this? 😍', 'avatar' => $image('avatars/lina.webp'), 'reply' => "It's 180 AED — sizes S to XL, delivered in 2 days.", 'action' => 'Order now', 'result' => '✓ Order #1042 placed']],
                        ['id' => 'comment', 'title' => 'Auto-DM from comments', 'description' => 'Comment ‘LINK’ → the link lands in DMs.', 'surface' => 'Instagram · Comments → DM', 'scene' => ['handle' => 'khalid.r', 'question' => 'LINK', 'avatar' => $image('avatars/khalid.webp'), 'transition' => '↓ sent to DMs', 'reply' => 'Here you go, Khalid 🔗 the full guide is one tap away.', 'action' => 'Open the link', 'result' => '✓ Link delivered in 0.4s']],
                        ['id' => 'giveaway', 'title' => 'Run giveaways', 'description' => 'Collect entries and pick winners automatically.', 'surface' => 'TikTok · Giveaway', 'scene' => ['handle' => 'nora.m', 'question' => 'Count me in! 🎉', 'reply' => "You're in, Nora! Winners are picked Friday at 8pm.", 'action' => 'Share for 2× entries', 'result' => '1,284 Entries collected']],
                    ]],
                    ['id' => 'try-it', 'type' => 'live_demo', 'kicker' => 'No signup needed', 'heading_lead' => "Don't take our word for it.", 'heading_mark' => 'Try it.', 'lead' => 'Write a comment like your customers do — watch BLEXCHAT answer it.', 'placeholder' => 'Write a comment…', 'bridge' => 'That flow took 0 minutes to build. Yours takes 2.', 'cta' => ['label' => 'Build your first flow', 'url' => '/signup'], 'handle' => 'you', 'initial' => 'Y', 'suggestions' => ['How much is this? 😍', 'Do you ship to Riyadh?', 'Link please 🙏'], 'replies' => [['id' => 'price', 'reply' => 'Hey! Just sent the price to your DMs 💌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'], ['id' => 'shipping', 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order', 'result' => '✓ Replied in 0.4s'], ['id' => 'link', 'reply' => 'Sent! Check your DMs ✨', 'action' => 'Open link', 'result' => '✓ Replied in 0.4s'], ['id' => 'stock', 'reply' => 'Still in stock — I saved one for you 🙌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'], ['id' => 'booking', 'reply' => 'Done — pick a time and I will confirm it 📅', 'action' => 'Pick a time', 'result' => '✓ Replied in 0.4s'], ['id' => 'fallback', 'reply' => 'Got it — sending you the details right now 💌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s']]],
                    ['id' => 'get-started', 'type' => 'steps', 'heading' => 'Live in minutes, not weeks.', 'steps' => [['number' => '01', 'name' => 'Sign up free', 'line' => 'No credit card, no calls.', 'loop' => 'tick'], ['number' => '02', 'name' => 'Connect your channels', 'line' => 'Instagram, WhatsApp, TikTok, Messenger — one tap each.', 'loop' => 'channels'], ['number' => '03', 'name' => 'Go live', 'line' => 'Pick a flow, switch it on, watch replies happen.', 'loop' => 'switch']], 'cta' => ['label' => 'Get started free', 'url' => '/signup'], 'secondary_cta' => ['label' => 'See plans', 'url' => '/pricing']],
                    ['id' => 'numbers', 'type' => 'numbers', 'heading' => 'BLEXCHAT in numbers', 'illustrative' => true, 'stats' => [['value' => '1.2M+', 'label' => 'Messages automated'], ['value' => '0.4s', 'label' => 'Average reply time'], ['value' => '$2M+', 'label' => 'Revenue recovered'], ['value' => '2,000+', 'label' => 'Businesses']]],
                    ['id' => 'faq', 'type' => 'faq', 'heading' => 'Questions, answered.', 'items' => [
                        ['question' => 'How much does it cost?', 'answer' => 'There’s a free plan to start. Paid plans scale with your audience — see Pricing.', 'link' => ['label' => 'Pricing', 'url' => '/pricing']],
                        ['question' => 'Which channels are supported?', 'answer' => 'Instagram, WhatsApp, TikTok and Messenger — one inbox, one set of automations.'],
                        ['question' => 'Does it work in Arabic?', 'answer' => 'Fully. Flows, replies and the dashboard all support Arabic and English.'],
                        ['question' => 'Is it approved by Meta?', 'answer' => 'BLEXCHAT uses the official APIs.', 'claim_unverified' => true],
                        ['question' => 'How long does setup take?', 'answer' => 'Most businesses go live in under 10 minutes.', 'claim_unverified' => true],
                        ['question' => 'Is my data safe?', 'answer' => 'Your conversations stay yours. We never sell data.', 'claim_unverified' => true],
                    ]],
                ],
                'demo_replies' => [
                    'note' => 'Shared by the hero composer and the try-it section. Match keywords case-insensitively as substrings; first matching rule wins.',
                    'rules' => [
                        ['id' => 'price', 'keywords' => ['how much', 'price', 'cost', 'بكم', 'كم سعر', 'السعر'], 'reply' => 'Hey! Just sent the price to your DMs 💌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'],
                        ['id' => 'shipping', 'keywords' => ['ship', 'deliver', 'delivery', 'riyadh', 'jeddah', 'ksa', 'توصيل', 'شحن', 'الرياض', 'جدة'], 'reply' => 'Yes! Free delivery across KSA 🚚', 'action' => 'Track order', 'result' => '✓ Replied in 0.4s'],
                        ['id' => 'link', 'keywords' => ['link', 'url', 'website', 'رابط', 'لينك', 'الموقع'], 'reply' => 'Sent! Check your DMs ✨', 'action' => 'Open link', 'result' => '✓ Replied in 0.4s'],
                        ['id' => 'stock', 'keywords' => ['available', 'in stock', 'sold out', 'size', 'متوفر', 'موجود', 'مقاس'], 'reply' => 'Still in stock — I saved one for you 🙌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'],
                        ['id' => 'booking', 'keywords' => ['book', 'booking', 'appointment', 'reserve', 'table', 'حجز', 'موعد', 'احجز'], 'reply' => 'Done — pick a time and I will confirm it 📅', 'action' => 'Pick a time', 'result' => '✓ Replied in 0.4s'],
                    ],
                    'fallback' => ['id' => 'fallback', 'reply' => 'Got it — sending you the details right now 💌', 'action' => 'View product', 'result' => '✓ Replied in 0.4s'],
                ],
                'footer' => ['brand' => 'Blexchat', 'tagline' => 'Turn comments and DMs into customers.', 'columns' => [
                    ['title' => 'Product', 'links' => [['label' => 'Features', 'url' => '#features'], ['label' => 'Pricing', 'url' => '/pricing'], ['label' => 'Blexchat AI', 'url' => '#try-it']]],
                    ['title' => 'Solutions', 'links' => [['label' => 'Creators', 'url' => '/solutions/creators'], ['label' => 'E-commerce', 'url' => '/solutions/e-commerce'], ['label' => 'Restaurants', 'url' => '/solutions/restaurants'], ['label' => 'Agencies', 'url' => '/solutions/agencies']]],
                    ['title' => 'Account', 'links' => [['label' => 'Sign up', 'url' => '/signup'], ['label' => 'Sign in', 'url' => '/signin']]],
                ], 'giant_lead' => 'Made for', 'giant_words' => ['Creators', 'Brands', 'Sellers', 'Agencies'], 'cta' => ['label' => 'Get started free', 'url' => '/signup'], 'copyright' => '© 2026 BLEXCHAT', 'legal' => [['label' => 'Privacy', 'url' => '/privacy'], ['label' => 'Terms', 'url' => '/terms']], 'languages' => ['EN', 'العربية']],
            ]]
        );
    }
}
