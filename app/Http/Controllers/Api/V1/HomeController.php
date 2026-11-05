<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComparisonPoint;
use App\Models\Creator;
use App\Models\DemoReplyRule;
use App\Models\FaqItem;
use App\Models\HomeBlock;
use App\Models\HomeStat;
use App\Models\UseCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Home', description: 'Public marketing homepage content')]
#[OA\Schema(schema: 'Link', description: 'Where something leads: exactly one of `to` (an app route, e.g. /signup) or `section` (a homepage section id, scrolled to). `tab` opens a tab of that section.', properties: [
    new OA\Property(property: 'to', type: 'string', example: '/signup'),
    new OA\Property(property: 'section', type: 'string', example: 'see-it-in-action'),
    new OA\Property(property: 'tab', type: 'string', example: 'comment'),
])]
#[OA\Schema(schema: 'Cta', description: 'A labelled link (button or text link).', allOf: [
    new OA\Schema(properties: [new OA\Property(property: 'label', type: 'string', example: 'Get started free')]),
    new OA\Schema(ref: '#/components/schemas/Link'),
])]
#[OA\Schema(schema: 'ChatLine', description: 'One comment or DM and the automated answer', properties: [
    new OA\Property(property: 'handle', type: 'string', example: 'omar.fit'),
    new OA\Property(property: 'avatar', type: 'string', format: 'uri'),
    new OA\Property(property: 'question', type: 'string'),
    new OA\Property(property: 'reply', type: 'string'),
    new OA\Property(property: 'action', type: 'string', description: 'Button label inside the reply card'),
    new OA\Property(property: 'result', type: 'string', description: 'Win pill shown after the reply; absent when there is none'),
])]
#[OA\Schema(schema: 'DemoReply', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'shipping'),
    new OA\Property(property: 'keywords', type: 'array', items: new OA\Items(type: 'string'), description: 'Absent on the fallback'),
    new OA\Property(property: 'reply', type: 'string'),
    new OA\Property(property: 'action', type: 'string'),
    new OA\Property(property: 'result', type: 'string'),
])]
#[OA\Schema(schema: 'HeroSection', description: 'type = hero', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'hero'),
    new OA\Property(property: 'type', type: 'string', enum: ['hero']),
    new OA\Property(property: 'headline_rotations', type: 'array', items: new OA\Items(type: 'array', items: new OA\Items(type: 'string')), description: 'Rotating headline: pairs of [first line, highlighted line]'),
    new OA\Property(property: 'handwriting', type: 'string'),
    new OA\Property(property: 'subtitle', type: 'string'),
    new OA\Property(property: 'description', type: 'string'),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'microcopy', type: 'string'),
    new OA\Property(property: 'photos', type: 'object', description: '`relax` is the main photo; `celebrate` is shown in the relief scene', properties: [
        new OA\Property(property: 'relax', type: 'string', format: 'uri'),
        new OA\Property(property: 'celebrate', type: 'string', format: 'uri'),
    ]),
    new OA\Property(property: 'cutout', type: 'string', format: 'uri', description: 'The person cut out of the main photo'),
    new OA\Property(property: 'story', type: 'object', description: 'Copy of the pressure → working → relief loop', properties: [
        new OA\Property(property: 'pressure', type: 'object', properties: [
            new OA\Property(property: 'counter', type: 'string', example: 'new messages', description: 'Kept for older clients; use the plural forms'),
            new OA\Property(property: 'counter_one', type: 'string', example: 'new message', description: 'Plural forms, keyed as i18next reads them: pick by `count` with Intl.PluralRules. English sends one and other; Arabic also counter_zero, counter_two, counter_few and counter_many'),
            new OA\Property(property: 'counter_other', type: 'string', example: 'new messages'),
            new OA\Property(property: 'count', type: 'integer', example: 47, description: 'Counted up to; the relief scene clears this many'),
            new OA\Property(property: 'more', type: 'string', example: '+44 more'),
            new OA\Property(property: 'chips', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'handle', type: 'string'),
                new OA\Property(property: 'text', type: 'string'),
            ])),
        ]),
        new OA\Property(property: 'working_badge', type: 'string'),
        new OA\Property(property: 'relief', type: 'object', properties: [
            new OA\Property(property: 'stats', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'string'),
                new OA\Property(property: 'to', type: 'integer', description: 'Counted up to'),
                new OA\Property(property: 'suffix', type: 'string', description: 'Shown after the number; may be empty'),
                new OA\Property(property: 'label', type: 'string', description: 'Kept for older clients; use the plural forms'),
                new OA\Property(property: 'label_one', type: 'string', example: 'reply', description: 'Plural forms by `to`, like counter_one…: label_one and label_other, plus label_zero, label_two, label_few and label_many in Arabic'),
                new OA\Property(property: 'label_other', type: 'string', example: 'replies'),
            ])),
        ]),
        new OA\Property(property: 'visitor_result', type: 'string'),
    ]),
    new OA\Property(property: 'composer', type: 'object', properties: [
        new OA\Property(property: 'label', type: 'string'),
        new OA\Property(property: 'placeholder_prefix', type: 'string', description: 'Typewriter prefix; each example is typed after it'),
        new OA\Property(property: 'placeholder', type: 'string', description: 'Static placeholder when motion is reduced or the field is focused'),
        new OA\Property(property: 'sr_label', type: 'string'),
        new OA\Property(property: 'send_label', type: 'string'),
        new OA\Property(property: 'examples', type: 'array', items: new OA\Items(type: 'string')),
    ]),
    new OA\Property(property: 'social_proof', type: 'object', properties: [
        new OA\Property(property: 'rating', type: 'string'),
        new OA\Property(property: 'count', type: 'string'),
        new OA\Property(property: 'avatars', type: 'array', items: new OA\Items(type: 'string', format: 'uri')),
    ]),
    new OA\Property(property: 'channels_label', type: 'string'),
    new OA\Property(property: 'channels', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger'], description: 'Picks the brand mark'),
        new OA\Property(property: 'label', type: 'string'),
    ])),
    new OA\Property(property: 'partners', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', enum: ['meta', 'tiktok'], description: 'Picks the partner mark'),
        new OA\Property(property: 'label', type: 'string'),
    ])),
    new OA\Property(property: 'conversations', type: 'array', items: new OA\Items(allOf: [
        new OA\Schema(ref: '#/components/schemas/ChatLine'),
        new OA\Schema(properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'channel', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
            new OA\Property(property: 'initial', type: 'string', description: 'Shown when there is no avatar'),
        ]),
    ])),
])]
#[OA\Schema(schema: 'CreatorStripSection', description: 'type = creator_strip', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'creators'),
    new OA\Property(property: 'type', type: 'string', enum: ['creator_strip']),
    new OA\Property(property: 'label', type: 'string'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'handle', type: 'string', example: '@noraqtn'),
        new OA\Property(property: 'followers', type: 'string'),
        new OA\Property(property: 'quote', type: 'string'),
        new OA\Property(property: 'photo', type: 'string', format: 'uri'),
    ])),
])]
#[OA\Schema(schema: 'IntroSection', description: 'type = intro', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'intro'),
    new OA\Property(property: 'type', type: 'string', enum: ['intro']),
    new OA\Property(property: 'headline', type: 'array', items: new OA\Items(type: 'string'), description: 'Two lines'),
    new OA\Property(property: 'lead', type: 'string'),
])]
#[OA\Schema(schema: 'VerticalSliderSection', description: 'type = vertical_slider. Each slide has its own scene, keyed by slide id.', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'features'),
    new OA\Property(property: 'type', type: 'string', enum: ['vertical_slider']),
    new OA\Property(property: 'slides_label', type: 'string', description: 'Accessible name for the slider'),
    new OA\Property(property: 'slides', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', enum: ['comments', 'dms', 'stories', 'channels']),
        new OA\Property(property: 'index', type: 'string', example: '01'),
        new OA\Property(property: 'headline', type: 'string'),
        new OA\Property(property: 'body', type: 'string'),
        new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
        new OA\Property(property: 'phone_label', type: 'string'),
        new OA\Property(property: 'channel', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
        new OA\Property(property: 'scene', type: 'object', description: 'comments: post, post_photo, comments[{handle,text}], reply, action, order · dms: ChatLine fields + seen, lead{name,detail,tag} · stories: story, photo, handle, reaction, reply, action · channels: inbox, total, total_label, tiles[{id,count}], footer'),
    ])),
])]
#[OA\Schema(schema: 'GiantWordSection', description: 'type = giant_word', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'autopilot'),
    new OA\Property(property: 'type', type: 'string', enum: ['giant_word']),
    new OA\Property(property: 'kicker', type: 'string'),
    new OA\Property(property: 'word', type: 'array', items: new OA\Items(type: 'string'), description: 'Two lines'),
    new OA\Property(property: 'mark', type: 'string'),
    new OA\Property(property: 'photo', type: 'string', format: 'uri'),
])]
#[OA\Schema(schema: 'UseCasesSection', description: 'type = use_cases. Each card links to /solutions/{slug}.', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'use-cases'),
    new OA\Property(property: 'type', type: 'string', enum: ['use_cases']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'cta_label', type: 'string', description: 'Button text on every card'),
    new OA\Property(property: 'prev_label', type: 'string', description: 'Accessible name of the previous arrow'),
    new OA\Property(property: 'next_label', type: 'string', description: 'Accessible name of the next arrow'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'slug', type: 'string', example: 'e-commerce'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'line', type: 'string'),
        new OA\Property(property: 'shape', type: 'string', enum: ['rounded', 'blob', 'arch', 'circle', 'pill', 'house']),
        new OA\Property(property: 'tone', type: 'string', enum: ['brand', 'tint', 'ink']),
    ])),
])]
#[OA\Schema(schema: 'ComparisonSection', description: 'type = comparison', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'before-after'),
    new OA\Property(property: 'type', type: 'string', enum: ['comparison']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'before', type: 'object', properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'rows', type: 'array', items: new OA\Items(type: 'string'))]),
    new OA\Property(property: 'after', type: 'object', properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'rows', type: 'array', items: new OA\Items(type: 'string'))]),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
])]
#[OA\Schema(schema: 'FlowTabsSection', description: 'type = flow_tabs', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'see-it-in-action'),
    new OA\Property(property: 'type', type: 'string', enum: ['flow_tabs']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'lead', type: 'string'),
    new OA\Property(property: 'composer_placeholder', type: 'string'),
    new OA\Property(property: 'tabs_label', type: 'string', description: 'Accessible name for the tab list'),
    new OA\Property(property: 'tabs', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', enum: ['follow', 'welcome', 'faq', 'comment', 'giveaway'], description: 'Matches `tab` in links'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'surface', type: 'string'),
        new OA\Property(property: 'sr', type: 'string', description: 'Screen-reader description of the scene'),
        new OA\Property(property: 'scene', type: 'object', description: 'ChatLine fields, plus per tab: follow → event · welcome → lead{tag,name,detail} · faq → event · comment → transition · giveaway → counter{channel,value,label}'),
    ])),
])]
#[OA\Schema(schema: 'LiveDemoSection', description: 'type = live_demo. Answers come from the top-level `demo_replies`.', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'try-it'),
    new OA\Property(property: 'type', type: 'string', enum: ['live_demo']),
    new OA\Property(property: 'kicker', type: 'string'),
    new OA\Property(property: 'heading_lead', type: 'string'),
    new OA\Property(property: 'heading_mark', type: 'string'),
    new OA\Property(property: 'lead', type: 'string'),
    new OA\Property(property: 'placeholder', type: 'string'),
    new OA\Property(property: 'bridge', type: 'string'),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'handle', type: 'string', description: 'Name shown on the visitor’s own comment'),
    new OA\Property(property: 'initial', type: 'string'),
    new OA\Property(property: 'suggestions', type: 'array', items: new OA\Items(type: 'string')),
])]
#[OA\Schema(schema: 'StepsSection', description: 'type = steps', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'get-started'),
    new OA\Property(property: 'type', type: 'string', enum: ['steps']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'steps', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'number', type: 'string', example: '01'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'line', type: 'string'),
        new OA\Property(property: 'loop', type: 'string', enum: ['tick', 'channels', 'switch'], description: 'Which small animation the step shows'),
    ])),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'secondary_cta', ref: '#/components/schemas/Cta'),
])]
#[OA\Schema(schema: 'NumbersSection', description: 'type = numbers', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'numbers'),
    new OA\Property(property: 'type', type: 'string', enum: ['numbers']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'stats', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'value', type: 'string', example: '1.2M+'),
        new OA\Property(property: 'label', type: 'string'),
    ])),
])]
#[OA\Schema(schema: 'FaqSection', description: 'type = faq', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'faq'),
    new OA\Property(property: 'type', type: 'string', enum: ['faq']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'question', type: 'string'),
        new OA\Property(property: 'answer', type: 'string', description: 'When the item has a link, `{link}` marks where it goes in the text'),
        new OA\Property(property: 'link', ref: '#/components/schemas/Cta', description: 'Absent when the answer has no link'),
    ])),
])]
#[OA\Schema(schema: 'MarketingHome', properties: [
    new OA\Property(property: 'sections', type: 'array', description: 'In display order. Switch on `type`.', items: new OA\Items(oneOf: [
        new OA\Schema(ref: '#/components/schemas/HeroSection'),
        new OA\Schema(ref: '#/components/schemas/CreatorStripSection'),
        new OA\Schema(ref: '#/components/schemas/IntroSection'),
        new OA\Schema(ref: '#/components/schemas/VerticalSliderSection'),
        new OA\Schema(ref: '#/components/schemas/GiantWordSection'),
        new OA\Schema(ref: '#/components/schemas/UseCasesSection'),
        new OA\Schema(ref: '#/components/schemas/ComparisonSection'),
        new OA\Schema(ref: '#/components/schemas/FlowTabsSection'),
        new OA\Schema(ref: '#/components/schemas/LiveDemoSection'),
        new OA\Schema(ref: '#/components/schemas/StepsSection'),
        new OA\Schema(ref: '#/components/schemas/NumbersSection'),
        new OA\Schema(ref: '#/components/schemas/FaqSection'),
    ])),
    new OA\Property(property: 'demo_replies', type: 'object', description: 'Answers for the hero composer and the live demo. Match keywords case-insensitively as substrings, rules in order; the first match answers, otherwise the fallback.', properties: [
        new OA\Property(property: 'card', type: 'object', description: 'Header of every auto-reply card on the page', properties: [
            new OA\Property(property: 'label', type: 'string', example: 'Auto-reply'),
            new OA\Property(property: 'timer', type: 'string', example: '0.4s'),
        ]),
        new OA\Property(property: 'rules', type: 'array', items: new OA\Items(ref: '#/components/schemas/DemoReply')),
        new OA\Property(property: 'fallback', ref: '#/components/schemas/DemoReply'),
    ]),
    new OA\Property(property: 'navigation', ref: '#/components/schemas/SiteNavigation', nullable: true),
    new OA\Property(property: 'footer', ref: '#/components/schemas/SiteFooter', nullable: true),
])]
#[OA\Schema(schema: 'MegaMenuItem', description: 'One entry of a mega menu, with its link fields (`to`, or `section`).', allOf: [
    new OA\Schema(properties: [
        new OA\Property(property: 'id', type: 'string', example: 'instagram'),
        new OA\Property(property: 'title', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'desc', type: 'string', example: 'Turn comments and DMs into customers'),
        new OA\Property(property: 'channel', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger'], description: 'Shows the channel’s brand mark; absent on the others'),
        new OA\Property(property: 'icon', type: 'string', enum: ['spark', 'camera', 'bag', 'cup', 'tooth', 'briefcase', 'cap', 'house', 'headset'], description: 'One of our line icons; absent on channels'),
        new OA\Property(property: 'cta', type: 'string', description: 'Only on an entry set apart on its own row (Blexchat AI)', example: 'Try it live'),
    ]),
    new OA\Schema(ref: '#/components/schemas/Link'),
])]
#[OA\Schema(schema: 'SiteNavigation', description: 'The marketing sites’ top bar and its two mega menus, the same on every marketing page.', properties: [
    new OA\Property(property: 'items', type: 'array', description: 'Bar entries in order; each either opens a menu (`menu`) or is a link (`to`)', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', example: 'product'),
        new OA\Property(property: 'label', type: 'string', example: 'Product'),
        new OA\Property(property: 'menu', type: 'string', enum: ['product', 'solutions'], description: 'The key in `menus` it opens'),
        new OA\Property(property: 'to', type: 'string', example: '/pricing'),
    ])),
    new OA\Property(property: 'menus', type: 'object', properties: [
        new OA\Property(property: 'product', type: 'object', properties: [
            new OA\Property(property: 'heading', type: 'string'),
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/MegaMenuItem')),
        ]),
        new OA\Property(property: 'solutions', type: 'object', properties: [
            new OA\Property(property: 'heading', type: 'string'),
            new OA\Property(property: 'columns', type: 'boolean', description: 'Laid out in two columns, read down'),
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/MegaMenuItem')),
        ]),
    ]),
    new OA\Property(property: 'sign_in', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'get_started', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'get_started_mobile', ref: '#/components/schemas/Cta', description: 'The button at the foot of the mobile menu'),
    new OA\Property(property: 'aria', type: 'object', description: 'Screen-reader names', properties: [
        new OA\Property(property: 'main', type: 'string'),
        new OA\Property(property: 'mobile', type: 'string'),
        new OA\Property(property: 'open_menu', type: 'string'),
        new OA\Property(property: 'close_menu', type: 'string'),
    ]),
])]
#[OA\Schema(schema: 'SiteFooter', description: 'The marketing sites’ footer, the same on every marketing page.', properties: [
    new OA\Property(property: 'tagline', type: 'string', example: 'Turn comments and DMs into customers.'),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'columns', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Product'),
        new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Cta')),
    ])),
    new OA\Property(property: 'made_for', type: 'string', example: 'Made for', description: 'Shown before the rotating giant words'),
    new OA\Property(property: 'made_for_sr', type: 'string', description: 'The whole sentence, for screen readers'),
    new OA\Property(property: 'giant_words', type: 'array', items: new OA\Items(type: 'string'), example: ['Creators', 'Brands', 'Sellers', 'Agencies']),
    new OA\Property(property: 'copyright', type: 'string', example: '© 2026 BLEXCHAT'),
    new OA\Property(property: 'legal', type: 'array', description: 'Privacy and Terms; their pages come from GET /api/v1/legal/{id}', items: new OA\Items(allOf: [
        new OA\Schema(properties: [new OA\Property(property: 'id', type: 'string', enum: ['privacy', 'terms'])]),
        new OA\Schema(ref: '#/components/schemas/Cta'),
    ])),
    new OA\Property(property: 'aria', type: 'object', description: 'Screen-reader names', properties: [
        new OA\Property(property: 'footer', type: 'string'),
        new OA\Property(property: 'home', type: 'string'),
    ]),
])]
class HomeController extends Controller
{
    private string $locale;

    private string $fallbackLocale;

    private bool $usedFallback = false;

    #[OA\Get(
        path: '/api/v1/home',
        summary: 'Get the complete public marketing homepage',
        description: <<<'MD'
            Every homepage section in display order, the live-demo reply rules, and the top bar and footer every marketing page shares. No authentication.

            **Language**
            - Send `?lang=ar` for Arabic or `?lang=en` for English.
            - Without `lang`, the `Accept-Language` header is used (`ar`, `ar-SA`, `en-US`…).
            - A missing or unsupported language returns English.
            - The response states the language it was served in: `meta.locale` and the `Content-Language` header.
            - Any text not yet translated is served in English, field by field, and `meta.fallback` is then `true`.
            MD,
        tags: ['Home'],
        parameters: [
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response, and of the email where one is sent. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
            new OA\Parameter(name: 'Accept-Language', in: 'header', required: false, description: 'Used only when `lang` is absent; the first two letters are read (ar-SA → ar).', schema: new OA\Schema(type: 'string'), example: 'ar'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complete homepage content',
                headers: [new OA\Header(header: 'Content-Language', description: 'Language of the returned content', schema: new OA\Schema(type: 'string'))],
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/MarketingHome'),
                    new OA\Property(property: 'meta', type: 'object', properties: [
                        new OA\Property(property: 'locale', type: 'string', enum: ['en', 'ar'], description: 'Language the content was served in', example: 'ar'),
                        new OA\Property(property: 'fallback', type: 'boolean', description: 'True when at least one text is not translated yet and is served in English'),
                        new OA\Property(property: 'available_locales', type: 'array', description: 'Every value `lang` accepts', items: new OA\Items(type: 'string'), example: ['en', 'ar']),
                    ]),
                ]),
            ),
            new OA\Response(response: 404, description: 'Homepage content has not been seeded'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $blocks = HomeBlock::all()->keyBy('key');

        abort_if($blocks->isEmpty(), 404);

        $this->locale = App::getLocale();
        $this->fallbackLocale = config('app.fallback_locale');

        $data = [
            'sections' => $blocks->whereNotNull('sort')->sortBy('sort')->map(fn (HomeBlock $block): array => $this->section($block))->values()->all(),
            'demo_replies' => [...$this->block($blocks['demo_replies']), 'rules' => DemoReplyRule::visible()->get()->map(fn (DemoReplyRule $rule): array => [
                'id' => $rule->key,
                'keywords' => $rule->keywords,
                'reply' => $this->translate($rule, 'reply'),
                'action' => $this->translate($rule, 'action'),
                'result' => $this->translate($rule, 'result'),
            ])->all()],
            // Null until their blocks are seeded, so an older database still serves the page.
            'navigation' => $blocks->has('navigation') ? $this->block($blocks['navigation']) : null,
            'footer' => $blocks->has('footer') ? $this->block($blocks['footer']) : null,
        ];

        return response()
            ->json([
                'data' => $this->absoluteStorageUrls($this->frontendLinks($data)),
                'meta' => [
                    'locale' => $this->locale,
                    'fallback' => $this->usedFallback,
                    'available_locales' => config('app.supported_locales'),
                ],
            ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            // Text is sent unescaped, so the encoding is stated for clients that would otherwise guess it.
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Language', $this->locale);
    }

    /**
     * Content stores one `url` per link (a route like /pricing or a section like #try-it); the frontend
     * routes the two differently, so each becomes `to`, or `section` with an optional `tab`.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private function frontendLinks(array $node): array
    {
        if (is_string($node['url'] ?? null)) {
            $url = $node['url'];
            unset($node['url']);

            if (str_starts_with($url, '#')) {
                // parse_url() reads everything after # as the fragment, so the query is split by hand.
                [$section, $queryString] = array_pad(explode('?', substr($url, 1), 2), 2, '');
                parse_str($queryString, $query);
                $node += array_filter(['section' => $section, 'tab' => $query['tab'] ?? null]);
            } else {
                $node['to'] = $url;
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->frontendLinks($value);
            }
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function section(HomeBlock $block): array
    {
        $section = $this->block($block);

        return match ($block->key) {
            'creators' => [...$section, 'items' => Creator::visible()->get()->map(fn (Creator $creator): array => [
                'id' => $creator->key,
                'name' => $this->translate($creator, 'name'),
                'handle' => $creator->handle,
                'followers' => $this->translate($creator, 'followers'),
                'quote' => $this->translate($creator, 'quote'),
                'photo' => $creator->photo,
            ])->all()],
            'use-cases' => [...$section, 'items' => UseCase::visible()->get()->map(fn (UseCase $useCase): array => [
                'id' => $useCase->key,
                'slug' => $useCase->slug,
                'name' => $this->translate($useCase, 'name'),
                'line' => $this->translate($useCase, 'line'),
                'shape' => $useCase->shape,
                'tone' => $useCase->tone,
            ])->all()],
            'before-after' => $this->withComparisonPoints($section),
            'numbers' => [...$section, 'stats' => HomeStat::visible()->get()->map(fn (HomeStat $stat): array => [
                'value' => $this->translate($stat, 'value'),
                'label' => $this->translate($stat, 'label'),
            ])->all()],
            'faq' => [...$section, 'items' => FaqItem::visible()->get()->map(fn (FaqItem $item): array => array_filter([
                'question' => $this->translate($item, 'question'),
                'answer' => $this->translate($item, 'answer'),
                'link' => $item->link_url ? ['label' => $this->translate($item, 'link_label'), 'url' => $item->link_url] : null,
            ], fn (mixed $value): bool => $value !== null))->all()],
            default => $section,
        };
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function withComparisonPoints(array $section): array
    {
        $points = ComparisonPoint::visible()->get()->groupBy('side');

        foreach (ComparisonPoint::SIDES as $side) {
            $section[$side]['rows'] = $points->get($side, collect())->map(fn (ComparisonPoint $point): ?string => $this->translate($point, 'text'))->all();
        }

        return $section;
    }

    /**
     * A block's shared settings with its copy laid over them: the requested language
     * where translated, the fallback language for every text that is not.
     *
     * @return array<string, mixed>
     */
    private function block(HomeBlock $block): array
    {
        $texts = $block->getTranslations('texts');
        $fallback = $texts[$this->fallbackLocale] ?? [];
        $translated = $this->locale === $this->fallbackLocale ? [] : ($texts[$this->locale] ?? []);

        if ($this->locale !== $this->fallbackLocale && array_diff_key($this->textPaths($fallback), $this->textPaths($translated)) !== []) {
            $this->usedFallback = true;
        }

        return $this->asLists(array_replace_recursive($block->settings ?? [], $fallback, $translated));
    }

    private function translate(Model $model, string $attribute): ?string
    {
        $missing = $this->locale !== $this->fallbackLocale
            && ! $model->hasTranslation($attribute, $this->locale)
            && $model->hasTranslation($attribute, $this->fallbackLocale);

        if ($missing) {
            $this->usedFallback = true;
        }

        $value = $model->getTranslation($attribute, $this->locale);

        return $value === '' ? null : $value;
    }

    /**
     * Where each non-empty text sits ("story.pressure.counter"), compared by place rather than
     * counted, since a language may have texts another lacks (Arabic's extra plural forms).
     *
     * @param  array<array-key, mixed>  $texts
     * @return array<string, true>
     */
    private function textPaths(array $texts): array
    {
        return array_map(fn (): bool => true, array_filter(Arr::dot($texts), fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    /**
     * Settings and copy each hold part of a list; merged by index, keys must be restored to a JSON list.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private function asLists(array $node): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->asLists($value);
            }
        }

        if ($node !== [] && array_filter(array_keys($node), 'is_string') === []) {
            ksort($node);

            return array_values($node);
        }

        return $node;
    }

    /**
     * Images are stored as public-disk paths (so the admin can upload them); the frontend
     * runs on another origin, so it gets absolute URLs built from the request host.
     *
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    private function absoluteStorageUrls(array $content): array
    {
        array_walk_recursive($content, function (mixed &$value): void {
            if (is_string($value) && preg_match('#^marketing/[^\s]+\.(webp|png|jpe?g|gif|avif|svg)$#i', $value)) {
                $value = url('storage/'.$value);
            }
        });

        return $content;
    }
}
