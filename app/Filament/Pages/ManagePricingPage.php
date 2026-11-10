<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translations;
use App\Models\PricingPage;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * The pricing page's own words per language. Prices and limits come from the plans, the
 * comparison table and the FAQ have their own screens.
 */
class ManagePricingPage extends Page
{
    public static function pricingGroup(): string
    {
        return __('admin/pricing.pricing_page');
    }

    public static function figuresHelp(): string
    {
        return __('admin/pricing.filled_in_from_the_plans_max_saving_is_t');
    }

    /** @var array<string, list<array{0: string, 1: string, 2?: string}>> group label => [path, label, 'long'] */
    private static function texts(): array
    {
        return [
            __('admin/pricing.hero') => [
                ['hero.chip', __('admin/auth_screens.badge')], ['hero.title', __('admin/common.title')], ['hero.lead', __('admin/channel_screens.lead'), 'long'], ['hero.billing_label', __('admin/pricing.billing_period_label_screen_reader')],
                ['hero.monthly', __('admin/pricing.monthly')], ['hero.annual', __('admin/pricing.yearly')], ['hero.save', __('admin/pricing.saving')], ['hero.note', __('admin/pricing.note_under_the_cards')],
            ],
            __('admin/pricing.plan_cards') => [
                ['card.ribbon', __('admin/pricing.featured_plan_ribbon')], ['card.forever', __('admin/pricing.next_to_the_free_price')], ['card.per_month', __('admin/pricing.next_to_the_price')], ['card.no_card', __('admin/pricing.under_the_free_price')],
                ['card.billed_annually', __('admin/pricing.billed_yearly_total')], ['card.billed_monthly', __('admin/pricing.billed_monthly')], ['card.start_free', __('admin/pricing.free_plan_button')],
                ['card.try_free', __('admin/pricing.trial_button_days')], ['card.choose', __('admin/pricing.choose_button_plan')], ['card.contacts', __('admin/pricing.contacts_contacts')], ['card.ai', __('admin/pricing.ai_badge')],
            ],
            __('admin/pricing.comparison_table') => [
                ['compare.title', __('admin/common.title')], ['compare.feature', __('admin/pricing.feature_column_header_screen_reader')], ['compare.included', __('admin/pricing.meaning_of_the_tick_screen_reader')], ['compare.not_included', __('admin/pricing.meaning_of_the_dash_screen_reader')],
            ],
            __('admin/pricing.agencies_section') => [
                ['agency.title', __('admin/common.title')], ['agency.line', __('admin/pricing.line'), 'long'], ['agency.link_label', __('admin/home.link_text')],
            ],
            __('admin/common.faq') => [
                ['faq.title', __('admin/common.title')], ['faq.marked', __('admin/pricing.highlighted_word_in_the_title')],
            ],
        ];
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationLabel(): string
    {
        return __('admin/pricing.pricing_page_texts');
    }

    public function getTitle(): string
    {
        return __('admin/pricing.pricing_page_texts');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return self::pricingGroup();
    }

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'pricing-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $page = PricingPage::current();

        $this->form->fill([
            'texts' => array_replace(array_fill_keys(config('app.supported_locales'), []), $page?->getTranslations('texts') ?? []),
            'agency_url' => $page?->agency_url,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin/auth_screens.texts'))->description(__('admin/common.arabic_optional').' '.self::figuresHelp().' '.__('admin/pricing.card_figures_help'))->schema([
                    Tabs::make('languages')->tabs(array_map(
                        fn (string $locale): Tab => Tab::make(Translations::localeName($locale))->schema(array_map(
                            fn (string $group, array $fields): Fieldset => Fieldset::make($group)->schema(array_map(
                                fn (array $field): TextInput|Textarea => Translations::field(
                                    "texts.{$locale}.{$field[0]}",
                                    $field[1],
                                    $locale,
                                    required: Translations::isFallback($locale),
                                    long: ($field[2] ?? null) === 'long',
                                ),
                                $fields,
                            ))->columns(2),
                            array_keys(self::texts()),
                            self::texts(),
                        )),
                        config('app.supported_locales'),
                    )),
                ]),
                Section::make(__('admin/pricing.links_shared_by_both_languages'))->schema([
                    TextInput::make('agency_url')->label(__('admin/pricing.agencies_section_link'))->required()->helperText(__('admin/pricing.a_page_like_solutions_agencies')),
                ]),
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
        $texts = [];

        // A cleared text is dropped from its language, so that language falls back for it.
        foreach (config('app.supported_locales') as $locale) {
            $filled = array_filter(Arr::dot($state['texts'][$locale] ?? []), fn (mixed $value): bool => is_string($value) && $value !== '');

            if ($filled !== []) {
                $texts[$locale] = Arr::undot($filled);
            }
        }

        $page = PricingPage::current() ?? new PricingPage;
        $page->replaceTranslations('texts', $texts);
        $page->agency_url = $state['agency_url'];
        $page->save();

        Notification::make()->title(__('admin/pricing.pricing_page_texts_saved'))->success()->send();
    }
}
