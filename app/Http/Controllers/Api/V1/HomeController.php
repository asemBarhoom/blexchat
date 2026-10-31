<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComparisonPoint;
use App\Models\Creator;
use App\Models\DemoReplyRule;
use App\Models\FaqItem;
use App\Models\FooterLink;
use App\Models\HomeBlock;
use App\Models\HomeStat;
use App\Models\NavMenuItem;
use App\Models\UseCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Home', description: 'Public marketing homepage content')]
#[OA\Schema(schema: 'Cta', properties: [
    new OA\Property(property: 'label', type: 'string', example: 'Get started free'),
    new OA\Property(property: 'url', type: 'string', example: '/signup'),
])]
#[OA\Schema(schema: 'ChatLine', description: 'One comment or DM and the automated answer', properties: [
    new OA\Property(property: 'handle', type: 'string', example: 'omar.fit'),
    new OA\Property(property: 'avatar', type: 'string', format: 'uri', nullable: true),
    new OA\Property(property: 'question', type: 'string'),
    new OA\Property(property: 'reply', type: 'string'),
    new OA\Property(property: 'action', type: 'string', description: 'Button label inside the reply card'),
    new OA\Property(property: 'result', type: 'string', nullable: true, description: 'Win pill shown after the reply'),
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
    new OA\Property(property: 'headline_rotations', type: 'array', items: new OA\Items(type: 'array', items: new OA\Items(type: 'string')), description: 'Pairs of [lead, highlighted] words, rotated'),
    new OA\Property(property: 'handwriting', type: 'string'),
    new OA\Property(property: 'subtitle', type: 'string'),
    new OA\Property(property: 'description', type: 'string'),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'microcopy', type: 'string'),
    new OA\Property(property: 'photo', type: 'string', format: 'uri'),
    new OA\Property(property: 'cutout', type: 'string', format: 'uri'),
    new OA\Property(property: 'photos', type: 'object', properties: [
        new OA\Property(property: 'relax', type: 'string', format: 'uri'),
        new OA\Property(property: 'celebrate', type: 'string', format: 'uri'),
    ]),
    new OA\Property(property: 'story', type: 'object', description: 'The pressure → working → relief loop', properties: [
        new OA\Property(property: 'scenes', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', enum: ['pressure', 'working', 'relief']),
            new OA\Property(property: 'pose', type: 'string', enum: ['pressure', 'relax', 'celebrate']),
            new OA\Property(property: 'seconds', type: 'number'),
        ])),
        new OA\Property(property: 'pressure', type: 'object', properties: [
            new OA\Property(property: 'counter', type: 'string'),
            new OA\Property(property: 'count', type: 'integer'),
            new OA\Property(property: 'more', type: 'string'),
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
                new OA\Property(property: 'to', type: 'integer'),
                new OA\Property(property: 'suffix', type: 'string'),
                new OA\Property(property: 'label', type: 'string'),
            ])),
            new OA\Property(property: 'illustrative', type: 'boolean'),
        ]),
        new OA\Property(property: 'visitor_result', type: 'string'),
    ]),
    new OA\Property(property: 'composer', type: 'object', properties: [
        new OA\Property(property: 'label', type: 'string'),
        new OA\Property(property: 'placeholder_prefix', type: 'string', description: 'Typewriter prefix; examples are typed after it'),
        new OA\Property(property: 'placeholder', type: 'string', description: 'Static placeholder when motion is reduced or the field is focused'),
        new OA\Property(property: 'sr_label', type: 'string'),
        new OA\Property(property: 'send_label', type: 'string'),
        new OA\Property(property: 'examples', type: 'array', items: new OA\Items(type: 'string')),
    ]),
    new OA\Property(property: 'social_proof', type: 'object', properties: [
        new OA\Property(property: 'rating', type: 'string'),
        new OA\Property(property: 'count', type: 'string'),
        new OA\Property(property: 'illustrative', type: 'boolean'),
        new OA\Property(property: 'avatars', type: 'array', items: new OA\Items(type: 'string', format: 'uri')),
    ]),
    new OA\Property(property: 'channels_label', type: 'string'),
    new OA\Property(property: 'channels', type: 'array', items: new OA\Items(type: 'string')),
    new OA\Property(property: 'partners', type: 'array', items: new OA\Items(type: 'string')),
    new OA\Property(property: 'partner_claims_unverified', type: 'boolean'),
    new OA\Property(property: 'conversations', type: 'array', items: new OA\Items(allOf: [
        new OA\Schema(ref: '#/components/schemas/ChatLine'),
        new OA\Schema(properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'channel', type: 'string'),
            new OA\Property(property: 'initial', type: 'string'),
        ]),
    ])),
])]
#[OA\Schema(schema: 'CreatorStripSection', description: 'type = creator_strip', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'creators'),
    new OA\Property(property: 'type', type: 'string', enum: ['creator_strip']),
    new OA\Property(property: 'label', type: 'string'),
    new OA\Property(property: 'illustrative', type: 'boolean'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'handle', type: 'string'),
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
#[OA\Schema(schema: 'VerticalSliderSection', description: 'type = vertical_slider. Each slide has its own scene shape keyed by slide id (comments, dms, stories, channels).', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'features'),
    new OA\Property(property: 'type', type: 'string', enum: ['vertical_slider']),
    new OA\Property(property: 'total', type: 'integer'),
    new OA\Property(property: 'slides_label', type: 'string', description: 'Accessible name for the slider'),
    new OA\Property(property: 'slides', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', enum: ['comments', 'dms', 'stories', 'channels']),
        new OA\Property(property: 'index', type: 'string', example: '01'),
        new OA\Property(property: 'headline', type: 'string'),
        new OA\Property(property: 'body', type: 'string'),
        new OA\Property(property: 'cta', type: 'string'),
        new OA\Property(property: 'target', type: 'string', description: 'In-page anchor or route'),
        new OA\Property(property: 'phone_label', type: 'string'),
        new OA\Property(property: 'channel', type: 'string'),
        new OA\Property(property: 'scene', type: 'object', description: 'comments: post, post_photo, comments[], reply, action, order · dms: ChatLine fields + seen, lead{name,detail,tag} · stories: story, photo, handle, reaction, reply, action · channels: inbox, total, total_label, tiles[{id,count}], footer. All may carry illustrative.'),
    ])),
])]
#[OA\Schema(schema: 'GiantWordSection', description: 'type = giant_word', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'autopilot'),
    new OA\Property(property: 'type', type: 'string', enum: ['giant_word']),
    new OA\Property(property: 'kicker', type: 'string'),
    new OA\Property(property: 'word', type: 'array', items: new OA\Items(type: 'string')),
    new OA\Property(property: 'mark', type: 'string'),
    new OA\Property(property: 'photo', type: 'string', format: 'uri'),
])]
#[OA\Schema(schema: 'UseCasesSection', description: 'type = use_cases', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'use-cases'),
    new OA\Property(property: 'type', type: 'string', enum: ['use_cases']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'cta', type: 'string'),
    new OA\Property(property: 'prev_label', type: 'string', description: 'Accessible name of the previous arrow'),
    new OA\Property(property: 'next_label', type: 'string', description: 'Accessible name of the next arrow'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'line', type: 'string'),
        new OA\Property(property: 'shape', type: 'string'),
        new OA\Property(property: 'tone', type: 'string'),
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
        new OA\Property(property: 'id', type: 'string', enum: ['follow', 'welcome', 'faq', 'comment', 'giveaway']),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'surface', type: 'string'),
        new OA\Property(property: 'sr', type: 'string', description: 'Screen-reader description of the scene'),
        new OA\Property(property: 'scene', type: 'object', description: 'ChatLine fields, plus per tab: follow → event · welcome → lead{tag,name,detail} · faq → event · comment → transition · giveaway → counter{channel,value,label}. May carry illustrative.'),
    ])),
])]
#[OA\Schema(schema: 'LiveDemoSection', description: 'type = live_demo', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'try-it'),
    new OA\Property(property: 'type', type: 'string', enum: ['live_demo']),
    new OA\Property(property: 'kicker', type: 'string'),
    new OA\Property(property: 'heading_lead', type: 'string'),
    new OA\Property(property: 'heading_mark', type: 'string'),
    new OA\Property(property: 'lead', type: 'string'),
    new OA\Property(property: 'placeholder', type: 'string'),
    new OA\Property(property: 'bridge', type: 'string'),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'handle', type: 'string'),
    new OA\Property(property: 'initial', type: 'string'),
    new OA\Property(property: 'suggestions', type: 'array', items: new OA\Items(type: 'string')),
    new OA\Property(property: 'replies_from', type: 'string', enum: ['demo_replies'], description: 'Answers come from the top-level demo_replies'),
])]
#[OA\Schema(schema: 'StepsSection', description: 'type = steps', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'get-started'),
    new OA\Property(property: 'type', type: 'string', enum: ['steps']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'steps', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'number', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'line', type: 'string'),
        new OA\Property(property: 'loop', type: 'string', enum: ['tick', 'channels', 'switch']),
    ])),
    new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
    new OA\Property(property: 'secondary_cta', ref: '#/components/schemas/Cta'),
])]
#[OA\Schema(schema: 'NumbersSection', description: 'type = numbers', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'numbers'),
    new OA\Property(property: 'type', type: 'string', enum: ['numbers']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'illustrative', type: 'boolean'),
    new OA\Property(property: 'stats', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'value', type: 'string'),
        new OA\Property(property: 'label', type: 'string'),
    ])),
])]
#[OA\Schema(schema: 'FaqSection', description: 'type = faq', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'faq'),
    new OA\Property(property: 'type', type: 'string', enum: ['faq']),
    new OA\Property(property: 'heading', type: 'string'),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'question', type: 'string'),
        new OA\Property(property: 'answer', type: 'string'),
        new OA\Property(property: 'link', ref: '#/components/schemas/Cta'),
        new OA\Property(property: 'claim_unverified', type: 'boolean'),
    ])),
])]
#[OA\Schema(schema: 'MarketingHome', properties: [
    new OA\Property(property: 'navigation', type: 'object', properties: [
        new OA\Property(property: 'brand', type: 'string'),
        new OA\Property(property: 'labels', type: 'object', description: 'Accessible names for the navbar controls', properties: [
            new OA\Property(property: 'home', type: 'string'),
            new OA\Property(property: 'main', type: 'string'),
            new OA\Property(property: 'mobile', type: 'string'),
            new OA\Property(property: 'open_menu', type: 'string'),
            new OA\Property(property: 'close_menu', type: 'string'),
            new OA\Property(property: 'language', type: 'string'),
            new OA\Property(property: 'language_button', type: 'string'),
        ]),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'label', type: 'string'),
            new OA\Property(property: 'menu', type: 'string', nullable: true, description: 'Key into menus'),
            new OA\Property(property: 'url', type: 'string', nullable: true),
        ])),
        new OA\Property(property: 'account', type: 'array', items: new OA\Items(ref: '#/components/schemas/Cta')),
        new OA\Property(property: 'languages', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'label', type: 'string'),
        ])),
        new OA\Property(property: 'menus', type: 'object', description: 'Keyed by menu id: { heading, items[{title, description, cta?, target?}] }'),
    ]),
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
    new OA\Property(property: 'demo_replies', type: 'object', description: 'Shared by the hero composer and the live demo. Match keywords case-insensitively as substrings; first rule wins, else fallback.', properties: [
        new OA\Property(property: 'card', type: 'object', description: 'Header of every auto-reply card on the page', properties: [
            new OA\Property(property: 'label', type: 'string', example: 'Auto-reply'),
            new OA\Property(property: 'timer', type: 'string', example: '0.4s'),
        ]),
        new OA\Property(property: 'note', type: 'string'),
        new OA\Property(property: 'rules', type: 'array', items: new OA\Items(ref: '#/components/schemas/DemoReply')),
        new OA\Property(property: 'fallback', ref: '#/components/schemas/DemoReply'),
    ]),
    new OA\Property(property: 'footer', type: 'object', properties: [
        new OA\Property(property: 'brand', type: 'string'),
        new OA\Property(property: 'home_label', type: 'string'),
        new OA\Property(property: 'nav_label', type: 'string'),
        new OA\Property(property: 'giant_label', type: 'string', description: 'Accessible text for the giant word rotation'),
        new OA\Property(property: 'tagline', type: 'string'),
        new OA\Property(property: 'columns', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'links', type: 'array', items: new OA\Items(ref: '#/components/schemas/Cta')),
        ])),
        new OA\Property(property: 'giant_lead', type: 'string'),
        new OA\Property(property: 'giant_words', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
        new OA\Property(property: 'copyright', type: 'string'),
        new OA\Property(property: 'legal', type: 'array', items: new OA\Items(ref: '#/components/schemas/Cta')),
        new OA\Property(property: 'languages', type: 'array', items: new OA\Items(type: 'string')),
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
        description: 'Navigation, every homepage section in display order, the live-demo reply rules and the footer. No authentication. Language comes from ?lang= or Accept-Language. Any text not yet translated into that language is served in English, field by field, and meta.fallback is then true.',
        tags: ['Home'],
        parameters: [
            new OA\Parameter(name: 'lang', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['en', 'ar'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complete homepage content',
                headers: [new OA\Header(header: 'Content-Language', description: 'Language of the returned content', schema: new OA\Schema(type: 'string'))],
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/MarketingHome'),
                    new OA\Property(property: 'meta', type: 'object', properties: [
                        new OA\Property(property: 'locale', type: 'string', description: 'Language of the returned content', example: 'ar'),
                        new OA\Property(property: 'fallback', type: 'boolean', description: 'True when at least one text is not translated yet and is served in English'),
                        new OA\Property(property: 'available_locales', type: 'array', items: new OA\Items(type: 'string'), example: ['en', 'ar']),
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
            'navigation' => $this->navigation($blocks['navigation']),
            'sections' => $blocks->whereNotNull('sort')->sortBy('sort')->map(fn (HomeBlock $block): array => $this->section($block))->values()->all(),
            'demo_replies' => [...$this->block($blocks['demo_replies']), 'rules' => DemoReplyRule::visible()->get()->map(fn (DemoReplyRule $rule): array => [
                'id' => $rule->key,
                'keywords' => $rule->keywords,
                'reply' => $this->translate($rule, 'reply'),
                'action' => $this->translate($rule, 'action'),
                'result' => $this->translate($rule, 'result'),
            ])->all()],
            'footer' => $this->footer($blocks['footer']),
        ];

        return response()
            ->json([
                'data' => $this->absoluteStorageUrls($data),
                'meta' => [
                    'locale' => $this->locale,
                    'fallback' => $this->usedFallback,
                    'available_locales' => config('app.supported_locales'),
                ],
            ])
            ->header('Content-Language', $this->locale);
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
                'claim_unverified' => $item->claim_unverified ?: null,
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
     * @return array<string, mixed>
     */
    private function navigation(HomeBlock $block): array
    {
        $navigation = $this->block($block);
        $items = NavMenuItem::visible()->get()->groupBy('menu');

        foreach (NavMenuItem::MENUS as $menu) {
            $navigation['menus'][$menu]['items'] = $items->get($menu, collect())->map(fn (NavMenuItem $item): array => array_filter([
                'title' => $this->translate($item, 'title'),
                'description' => $this->translate($item, 'description'),
                'cta' => $this->translate($item, 'cta'),
                'target' => $item->target,
            ], fn (mixed $value): bool => $value !== null))->values()->all();
        }

        return $navigation;
    }

    /**
     * @return array<string, mixed>
     */
    private function footer(HomeBlock $block): array
    {
        $footer = $this->block($block);
        $links = FooterLink::visible()->get()->groupBy('group');
        $linksOf = fn (string $group): array => $links->get($group, collect())->map(fn (FooterLink $link): array => [
            'label' => $this->translate($link, 'label'),
            'url' => $link->url,
        ])->values()->all();

        $footer['columns'] = array_map(fn (array $column): array => [
            'title' => $column['title'],
            'links' => $linksOf($column['group']),
        ], $footer['columns']);
        $footer['legal'] = $linksOf('legal');

        return $footer;
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

        if ($this->locale !== $this->fallbackLocale && $this->countTexts($translated) < $this->countTexts($fallback)) {
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
     * @param  array<array-key, mixed>  $texts
     */
    private function countTexts(array $texts): int
    {
        $count = 0;

        array_walk_recursive($texts, function (mixed $value) use (&$count): void {
            $count += is_string($value) && $value !== '' ? 1 : 0;
        });

        return $count;
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
