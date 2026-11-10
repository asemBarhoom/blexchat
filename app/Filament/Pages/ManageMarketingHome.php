<?php

namespace App\Filament\Pages;

use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\HomeBlock;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * The single texts and images of each homepage block, per language. Lists that can grow
 * (FAQ, creators, links…) have their own screens; ids, section types, shapes, timings and
 * anchors are not shown here and are kept as they are on save.
 */
class ManageMarketingHome extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    public static function getNavigationLabel(): string
    {
        return __('admin/home.section_texts_and_images');
    }

    public function getTitle(): string
    {
        return __('admin/home.home_page_section_texts_and_images');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'marketing-home';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $locales = config('app.supported_locales');

        $this->form->fill($this->blocks()->mapWithKeys(fn (HomeBlock $block): array => [$block->key => [
            'settings' => $block->settings ?? [],
            'texts' => array_replace(array_fill_keys($locales, []), $block->getTranslations('texts')),
        ]])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                // Only the blocks that are seeded: a block added later has no tab until its seeder runs.
                Tabs::make('blocks')->persistTabInQueryString()->tabs(array_map(
                    fn (string $key): Tab => $this->blockTab($key, ...$this->definition($key)),
                    array_values(array_intersect(array_keys(self::blockLabels()), $this->blocks()->pluck('key')->all())),
                )),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('admin/common.save'))->submit('save')->keyBindings(['mod+s']),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($this->blocks() as $block) {
            $edited = $state[$block->key] ?? [];
            $texts = $block->getTranslations('texts');

            // A cleared text is dropped from its language, so that language falls back for it.
            foreach (config('app.supported_locales') as $locale) {
                $texts[$locale] = $this->merge($texts[$locale] ?? [], $edited['texts'][$locale] ?? []);

                if ($texts[$locale] === []) {
                    unset($texts[$locale]);
                }
            }

            $block->replaceTranslations('texts', $texts);
            $block->settings = $this->merge($block->settings ?? [], $edited['settings'] ?? []);
            $block->save();
        }

        Notification::make()->title(__('admin/home.home_page_content_saved'))->success()->send();
    }

    /** @return array<string, string> */
    private static function blockLabels(): array
    {
        return [
            'hero' => __('admin/home.hero'),
            'creators' => __('admin/home.creators'),
            'intro' => __('admin/channel_screens.lead'),
            'features' => __('admin/home.features'),
            'autopilot' => 'Autopilot',
            'use-cases' => __('admin/home.use_cases'),
            'before-after' => __('admin/home.before_and_after'),
            'see-it-in-action' => __('admin/home.see_it_in_action'),
            'try-it' => __('admin/home.try_it_yourself'),
            'get-started' => __('admin/channel_screens.steps'),
            'numbers' => __('admin/home.numbers'),
            'faq' => __('admin/common.faq'),
            'demo_replies' => __('admin/home.demo_replies'),
            'navigation' => __('admin/home.top_menu'),
            'footer' => __('admin/home.footer'),
            'not_found' => __('admin/home.404_page'),
        ];
    }

    /**
     * @return Collection<int, HomeBlock>
     */
    private function blocks(): Collection
    {
        return HomeBlock::query()->whereIn('key', array_keys(self::blockLabels()))->get();
    }

    /**
     * @param  array<string, list<array{0: string, 1: string, 2?: string}>>  $texts  group label => [path, label, 'long'|'optional']
     * @param  list<Component|Field>  $shared
     */
    private function blockTab(string $key, array $texts, array $shared = []): Tab
    {
        $languages = Tabs::make("{$key}-languages")->tabs(array_map(
            fn (string $locale): Tab => Tab::make(Translations::localeName($locale))->schema(array_map(
                fn (string $group, array $fields): Fieldset => Fieldset::make($group)->schema(array_map(
                    fn (array $field): Field => Translations::field(
                        "{$key}.texts.{$locale}.{$field[0]}",
                        $field[1],
                        $locale,
                        required: Translations::isFallback($locale) && ($field[2] ?? null) !== 'optional',
                        long: ($field[2] ?? null) === 'long',
                    ),
                    $fields,
                ))->columns(2),
                array_keys($texts),
                $texts,
            )),
            config('app.supported_locales'),
        ));

        return Tab::make(self::blockLabels()[$key])->schema(array_filter([
            Section::make(__('admin/auth_screens.texts'))->description(__('admin/common.arabic_optional'))->schema([$languages]),
            $shared === [] ? null : Section::make(__('admin/home.images_links_and_numbers_shared_by_both'))->schema($shared)->columns(2),
        ]));
    }

    /**
     * What each block shows for translation, and its fields shared by every language.
     *
     * @return array{0: array<string, list<array<int, string>>>, 1: list<mixed>}
     */
    private function definition(string $key): array
    {
        $at = fn (string $path): string => "{$key}.settings.{$path}";
        $image = fn (string $path, string $label): FileUpload => HomeContentFields::image($at($path), $label);
        $url = fn (string $path, string $label): TextInput => TextInput::make($at($path))->label($label)->required()
            ->helperText(__('admin/home.a_page_like_pricing_or_a_home_section_li'));
        $number = fn (string $path, string $label): TextInput => TextInput::make($at($path))->label($label)->numeric()->integer()->required();
        $each = fn (int $count, \Closure $fields): array => array_merge(...array_map($fields, range(0, $count - 1)));

        return match ($key) {
            'hero' => [[
                __('admin/home.rotating_headline') => $each(3, fn (int $i): array => [["headline_rotations.{$i}.0", __('admin/home.first_part').($i + 1)], ["headline_rotations.{$i}.1", __('admin/home.highlighted_word').($i + 1)]]),
                __('admin/home.main_texts') => [['handwriting', __('admin/home.handwritten_word')], ['subtitle', __('admin/home.subtitle')], ['description', __('admin/common.description'), 'long'], ['microcopy', __('admin/home.text_under_the_button')], ['cta.label', __('admin/common.button_text')]],
                __('admin/home.animated_hero_story') => [
                    ['story.pressure.counter', __('admin/home.counter_text')], ...$this->pluralTexts('story.pressure.counter', __('admin/home.counter_text')), ['story.pressure.more', __('admin/home.more_text')], ['story.working_badge', __('admin/home.working_now_badge')], ['story.visitor_result', __('admin/home.visitor_demo_result')],
                    ...$each(3, fn (int $i): array => [["story.pressure.chips.{$i}.handle", __('admin/home.comment_account').($i + 1)], ["story.pressure.chips.{$i}.text", __('admin/home.comment').($i + 1)]]),
                    ...$each(3, fn (int $i): array => [
                        ["story.relief.stats.{$i}.label", __('admin/home.number_label').($i + 1)], ["story.relief.stats.{$i}.suffix", __('admin/home.number_suffix').($i + 1), 'optional'],
                        ...$this->pluralTexts("story.relief.stats.{$i}.label", __('admin/home.number_label').($i + 1)),
                    ]),
                ],
                __('admin/home.demo_box') => [
                    ['composer.label', __('admin/common.title')], ['composer.placeholder_prefix', __('admin/home.placeholder_start')], ['composer.placeholder', __('admin/home.fixed_placeholder')], ['composer.sr_label', __('admin/common.sr_label')], ['composer.send_label', __('admin/home.send_button_label')],
                    ...$each(3, fn (int $i): array => [["composer.examples.{$i}", __('admin/home.example').($i + 1)]]),
                ],
                __('admin/home.social_proof_and_channels') => [
                    ['social_proof.rating', __('admin/home.rating')], ['social_proof.count', __('admin/home.customer_count')], ['channels_label', __('admin/home.channels_title')],
                    ...$each(4, fn (int $i): array => [["channels.{$i}.label", __('admin/home.channel').($i + 1)]]),
                    ...$each(2, fn (int $i): array => [["partners.{$i}.label", __('admin/home.partner').($i + 1)]]),
                ],
                __('admin/home.animated_conversations') => $each(3, fn (int $i): array => [
                    ["conversations.{$i}.handle", __('admin/home.account').($i + 1)], ["conversations.{$i}.initial", __('admin/home.initial').($i + 1)],
                    ["conversations.{$i}.question", __('admin/home.customer_question').($i + 1)], ["conversations.{$i}.reply", __('admin/home.reply').($i + 1)],
                    ["conversations.{$i}.action", __('admin/home.reply_button').($i + 1)], ["conversations.{$i}.result", __('admin/home.result').($i + 1), 'optional'],
                ]),
            ], [
                $image('photos.relax', __('admin/home.main_photo_calm')), $image('photos.celebrate', __('admin/home.celebration_photo')),
                $image('cutout', __('admin/home.cut_out_person_photo')),
                $url('cta.url', __('admin/automations.button_url')), $number('story.pressure.count', __('admin/home.counter_number')),
                ...array_map(fn (int $i): TextInput => $number("story.relief.stats.{$i}.to", __('admin/home.number').($i + 1)), range(0, 2)),
                FileUpload::make($at('social_proof.avatars'))->label(__('admin/home.customer_photos'))->image()->multiple()->reorderable()
                    ->disk('public')->directory('marketing/home/uploads')->visibility('public')->columnSpanFull(),
                ...array_map(fn (int $i): FileUpload => $image("conversations.{$i}.avatar", __('admin/home.conversation_photo').($i + 1)), range(0, 2)),
            ]],
            'creators' => [[__('admin/common.title') => [['label', __('admin/common.title')]]]],
            'intro' => [[__('admin/channel_screens.lead') => [['headline.0', __('admin/home.first_line')], ['headline.1', __('admin/home.second_line')], ['lead', __('admin/common.description'), 'long']]]],
            'features' => [[
                __('admin/common.general') => [['slides_label', __('admin/common.sr_label')]],
                __('admin/home.01_comments') => [
                    ...$this->slideTexts(0),
                    ['slides.0.scene.post', __('admin/home.post_text')], ['slides.0.scene.reply', __('admin/common.reply')], ['slides.0.scene.action', __('admin/common.reply_button')], ['slides.0.scene.order', __('admin/home.resulting_order')],
                    ...$each(3, fn (int $i): array => [["slides.0.scene.comments.{$i}.handle", __('admin/home.comment_account').($i + 1)], ["slides.0.scene.comments.{$i}.text", __('admin/home.comment').($i + 1)]]),
                ],
                __('admin/home.02_messages') => [
                    ...$this->slideTexts(1),
                    ['slides.1.scene.handle', __('admin/common.account')], ['slides.1.scene.question', __('admin/common.question')], ['slides.1.scene.reply', __('admin/common.reply')], ['slides.1.scene.action', __('admin/common.reply_button')], ['slides.1.scene.seen', __('admin/home.seen_text')],
                    ['slides.1.scene.lead.tag', __('admin/home.lead_badge')], ['slides.1.scene.lead.name', __('admin/common.lead_name')], ['slides.1.scene.lead.detail', __('admin/common.lead_detail')],
                ],
                __('admin/home.03_story') => [
                    ...$this->slideTexts(2),
                    ['slides.2.scene.story', __('admin/home.story_text')], ['slides.2.scene.handle', __('admin/common.account')], ['slides.2.scene.reaction', __('admin/home.reaction')], ['slides.2.scene.reply', __('admin/common.reply')], ['slides.2.scene.action', __('admin/common.reply_button')],
                ],
                __('admin/home.04_channels') => [
                    ...$this->slideTexts(3),
                    ['slides.3.scene.inbox', __('admin/home.inbox_title')], ['slides.3.scene.total', __('admin/home.total')], ['slides.3.scene.total_label', __('admin/home.total_label')], ['slides.3.scene.footer', __('admin/auth_screens.fine')],
                    ...$each(4, fn (int $i): array => [["slides.3.scene.tiles.{$i}.count", __('admin/home.channel_count').($i + 1)]]),
                ],
            ], [
                ...array_map(fn (int $i): TextInput => $url("slides.{$i}.cta.url", __('admin/home.slide_button_link_0').($i + 1)), range(0, 3)),
                $image('slides.0.scene.post_photo', __('admin/home.slide_01_post_photo')),
                $image('slides.1.scene.avatar', __('admin/home.slide_02_photo')),
                $image('slides.2.scene.photo', __('admin/home.slide_03_story_photo')),
            ]],
            'autopilot' => [[__('admin/home.big_word') => [['kicker', __('admin/common.kicker')], ['mark', __('admin/home.mark')], ['word.0', __('admin/common.part_one')], ['word.1', __('admin/common.part_two')]]], [
                $image('photo', __('admin/common.photo')),
            ]],
            'use-cases' => [[__('admin/home.use_cases') => [['heading', __('admin/common.title')], ['cta_label', __('admin/home.card_button_text')], ['prev_label', __('admin/home.previous_arrow_label')], ['next_label', __('admin/home.next_arrow_label')]]]],
            'before-after' => [[__('admin/home.before_and_after') => [['heading', __('admin/common.title')], ['before.title', __('admin/home.before_title')], ['after.title', __('admin/home.after_title')], ['cta.label', __('admin/common.button_text')]]], [
                $url('cta.url', __('admin/automations.button_url')),
            ]],
            'see-it-in-action' => [[
                __('admin/common.general') => [['heading', __('admin/common.title')], ['lead', __('admin/common.description')], ['composer_placeholder', __('admin/home.message_box_text')], ['tabs_label', __('admin/common.sr_label')]],
                __('admin/home.welcome_new_followers') => [...$this->flowTexts(0), ['tabs.0.scene.event', __('admin/common.event')], ['tabs.0.scene.result', __('admin/common.result')]],
                __('admin/home.welcome_messages') => [...$this->flowTexts(1), ['tabs.1.scene.handle', __('admin/common.account')], ['tabs.1.scene.question', __('admin/common.question')], ['tabs.1.scene.lead.tag', __('admin/home.customer_badge')], ['tabs.1.scene.lead.name', __('admin/common.lead_name')], ['tabs.1.scene.lead.detail', __('admin/common.lead_detail')]],
                __('admin/home.automatic_faq') => [...$this->flowTexts(2), ['tabs.2.scene.event', __('admin/common.event')], ['tabs.2.scene.handle', __('admin/common.account')], ['tabs.2.scene.question', __('admin/common.question')], ['tabs.2.scene.result', __('admin/common.result')]],
                __('admin/home.dm_from_a_comment') => [...$this->flowTexts(3), ['tabs.3.scene.handle', __('admin/common.account')], ['tabs.3.scene.question', __('admin/home.comment_2')], ['tabs.3.scene.transition', __('admin/home.transition_text')], ['tabs.3.scene.result', __('admin/common.result')]],
                __('admin/home.giveaways') => [...$this->flowTexts(4), ['tabs.4.scene.handle', __('admin/common.account')], ['tabs.4.scene.question', __('admin/home.comment_2')], ['tabs.4.scene.counter.value', __('admin/home.count')], ['tabs.4.scene.counter.label', __('admin/home.count_label')]],
            ], array_map(fn (int $i): FileUpload => $image("tabs.{$i}.scene.avatar", __('admin/home.tab_photo').($i + 1)), range(0, 3))],
            'try-it' => [[
                __('admin/home.try_it_yourself') => [
                    ['kicker', __('admin/common.kicker')], ['heading_lead', __('admin/common.title')], ['heading_mark', __('admin/common.highlight')], ['lead', __('admin/common.description')], ['placeholder', __('admin/common.placeholder')],
                    ['bridge', __('admin/home.text_after_the_reply')], ['handle', __('admin/home.visitor_name')], ['initial', __('admin/common.initial')], ['cta.label', __('admin/common.button_text')],
                    ...$each(3, fn (int $i): array => [["suggestions.{$i}", __('admin/home.suggestion').($i + 1)]]),
                ],
            ], [
                $url('cta.url', __('admin/automations.button_url')),
            ]],
            'get-started' => [[
                __('admin/channel_screens.steps') => [
                    ['heading', __('admin/common.title')], ['cta.label', __('admin/home.main_button_text')], ['secondary_cta.label', __('admin/home.secondary_button_text')],
                    ...$each(3, fn (int $i): array => [["steps.{$i}.number", __('admin/home.step_number').($i + 1)], ["steps.{$i}.name", __('admin/home.step_name').($i + 1)], ["steps.{$i}.line", __('admin/home.step_description').($i + 1)]]),
                ],
            ], [
                $url('cta.url', __('admin/home.main_button_link')), $url('secondary_cta.url', __('admin/home.secondary_button_link')),
            ]],
            'numbers' => [[__('admin/home.numbers') => [['heading', __('admin/common.title')]]]],
            'faq' => [[__('admin/common.faq') => [['heading', __('admin/common.title')]]]],
            'demo_replies' => [[
                __('admin/home.reply_card') => [['card.label', __('admin/home.card_title')], ['card.timer', __('admin/home.reply_time')]],
                __('admin/home.default_reply_when_no_rule_matches') => [['fallback.reply', __('admin/common.reply')], ['fallback.action', __('admin/common.reply_button')], ['fallback.result', __('admin/common.result')]],
            ]],
            'navigation' => [[
                __('admin/home.bar') => [
                    ...$each(4, fn (int $i): array => [["items.{$i}.label", __('admin/home.link').($i + 1)]]),
                    ['sign_in.label', __('admin/home.sign_in_button')], ['get_started.label', __('admin/common.get_started_button')], ['get_started_mobile.label', __('admin/home.mobile_get_started_button')],
                    ['aria.main', __('admin/home.screen_reader_label_for_the_bar')], ['aria.mobile', __('admin/home.screen_reader_label_on_mobile')], ['aria.open_menu', __('admin/home.open_menu_label')], ['aria.close_menu', __('admin/home.close_menu_label')],
                ],
                __('admin/home.product_menu') => [
                    ['menus.product.heading', __('admin/common.title')],
                    ...$each(5, fn (int $i): array => [["menus.product.items.{$i}.title", __('admin/home.item').($i + 1)], ["menus.product.items.{$i}.desc", __('admin/home.item_description').($i + 1)]]),
                    ['menus.product.items.4.cta', __('admin/home.item_5_button_text')],
                ],
                __('admin/home.solutions_menu') => [
                    ['menus.solutions.heading', __('admin/common.title')],
                    ...$each(8, fn (int $i): array => [["menus.solutions.items.{$i}.title", __('admin/home.solution').($i + 1)], ["menus.solutions.items.{$i}.desc", __('admin/home.solution_description').($i + 1)]]),
                ],
            ], [
                $url('items.2.url', __('admin/home.link_3_url')), $url('items.3.url', __('admin/home.link_4_url')),
                $url('sign_in.url', __('admin/home.sign_in_link')), $url('get_started.url', __('admin/home.get_started_link')), $url('get_started_mobile.url', __('admin/home.mobile_get_started_link')),
                ...array_map(fn (int $i): TextInput => $url("menus.product.items.{$i}.url", __('admin/home.product_item_link').($i + 1)), range(0, 4)),
                ...array_map(fn (int $i): TextInput => $url("menus.solutions.items.{$i}.url", __('admin/home.solution_link').($i + 1)), range(0, 7)),
            ]],
            'footer' => [[
                __('admin/common.general') => [
                    ['tagline', __('admin/home.tagline')], ['cta.label', __('admin/common.button_text')], ['copyright', __('admin/auth_screens.copyright')],
                    ['made_for', __('admin/home.made_for_line')], ['made_for_sr', __('admin/home.full_line_for_screen_readers')],
                    ...$each(4, fn (int $i): array => [["giant_words.{$i}", __('admin/home.big_word_2').($i + 1)]]),
                    ['legal.0.label', __('admin/common.privacy_link')], ['legal.1.label', __('admin/auth_screens.legal_terms')],
                    ['aria.footer', __('admin/home.screen_reader_label_for_the_footer')], ['aria.home', __('admin/home.home_link_label')],
                ],
                ...collect([[0, __('admin/home.product_column'), 3], [1, __('admin/home.solutions_column'), 4], [2, __('admin/home.account_column'), 2]])->mapWithKeys(fn (array $column): array => [$column[1] => [
                    ["columns.{$column[0]}.title", __('admin/common.title')],
                    ...$each($column[2], fn (int $i): array => [["columns.{$column[0]}.links.{$i}.label", __('admin/home.link').($i + 1)]]),
                ]])->all(),
            ], [
                $url('cta.url', __('admin/automations.button_url')), $url('legal.0.url', __('admin/home.privacy_page_link')), $url('legal.1.url', __('admin/home.terms_page_link')),
                ...collect([[0, 3], [1, 4], [2, 2]])->flatMap(fn (array $column): array => array_map(
                    fn (int $i): TextInput => $url("columns.{$column[0]}.links.{$i}.url", __('admin/home.column_link').($column[0] + 1).' — '.($i + 1)),
                    range(0, $column[1] - 1),
                ))->all(),
            ]],
            'not_found' => [[
                __('admin/home.comment_and_reply') => [['question', __('admin/home.comment_2')], ['replied', __('admin/home.reply_time')], ['answer', __('admin/common.reply'), 'long']],
                __('admin/common.page') => [['title', __('admin/common.title')], ['body', __('admin/common.text'), 'long'], ['cta.label', __('admin/common.button_text')]],
            ], [
                TextInput::make($at('code'))->label(__('admin/coupons.code'))->required()->maxLength(10),
                $url('cta.url', __('admin/automations.button_url')),
            ]],
        };
    }

    /**
     * The forms of a counted word, keyed as i18next reads them (counter_one, counter_few…).
     * English needs one and other; Arabic also zero, two, few and many.
     *
     * @return list<array<int, string>>
     */
    private function pluralTexts(string $path, string $label): array
    {
        return array_map(
            fn (string $form): array => ["{$path}_{$form}", "{$label} — {$form}", 'optional'],
            ['zero', 'one', 'two', 'few', 'many', 'other'],
        );
    }

    /**
     * @return list<array<int, string>>
     */
    private function slideTexts(int $slide): array
    {
        return [
            ["slides.{$slide}.index", __('admin/home.number_2')], ["slides.{$slide}.headline", __('admin/common.title')], ["slides.{$slide}.body", __('admin/common.text'), 'long'],
            ["slides.{$slide}.cta.label", __('admin/common.button_text')], ["slides.{$slide}.phone_label", __('admin/home.phone_screen_title')],
        ];
    }

    /**
     * @return list<array<int, string>>
     */
    private function flowTexts(int $tab): array
    {
        return [
            ["tabs.{$tab}.title", __('admin/common.title')], ["tabs.{$tab}.description", __('admin/common.description')], ["tabs.{$tab}.surface", __('admin/common.platform')],
            ["tabs.{$tab}.sr", __('admin/home.screen_reader_description')], ["tabs.{$tab}.scene.reply", __('admin/common.reply')], ["tabs.{$tab}.scene.action", __('admin/common.reply_button')],
        ];
    }

    /**
     * Lays edited values over the stored ones so values the form does not show survive.
     */
    private function merge(mixed $original, mixed $edited): mixed
    {
        if (! is_array($edited)) {
            if (is_int($original) && is_numeric($edited)) {
                return (int) $edited;
            }

            // The form reports a blank input as null; keep an intentionally empty string as it was.
            if ($edited === null && $original === '') {
                return '';
            }

            return $edited;
        }

        // A translation may not have this branch yet.
        $original = is_array($original) ? $original : [];

        // A block whose shared values have no fields in the form sends nothing for them.
        if ($edited === []) {
            return $original;
        }

        // A list of plain values (e.g. several uploaded images) is replaced, minus blank entries;
        // anything structured is merged by position, as the form shows only some fields of some items.
        if (array_is_list($edited) && array_filter($edited, 'is_array') === []) {
            return array_filter($edited, fn (mixed $value): bool => $value !== null);
        }

        foreach ($edited as $key => $value) {
            $merged = $this->merge($original[$key] ?? null, $value);

            if ($merged === null || $merged === []) {
                unset($original[$key]);
            } else {
                $original[$key] = $merged;
            }
        }

        return $original;
    }
}
