<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translations;
use App\Models\HomeBlock;
use App\Models\Setting;
use App\Services\Referrals\ReferralProgram;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The referral program's rules, and the copy of its public page and of the referrer's screen, per language.
 */
class ManageReferralProgram extends Page
{
    /** Rows of each list on the page, as the page is designed. */
    private const STEPS = 3;

    private const BENEFITS = 4;

    private const QUESTIONS = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $slug = 'referral-program';

    protected static ?int $navigationSort = 0;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin/referrals.settings_nav');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/referrals.nav_group');
    }

    public function getTitle(): string
    {
        return __('admin/referrals.settings_title');
    }

    public function mount(): void
    {
        $block = HomeBlock::where('key', ReferralProgram::BLOCK)->first();

        $this->form->fill([
            'enabled' => (bool) Setting::get(Setting::REFERRAL_ENABLED),
            'rate' => Setting::get(Setting::REFERRAL_RATE),
            'commission' => Setting::get(Setting::REFERRAL_COMMISSION),
            'months' => Setting::get(Setting::REFERRAL_MONTHS),
            'cookie_days' => Setting::get(Setting::REFERRAL_COOKIE_DAYS),
            'min_payout' => Setting::get(Setting::REFERRAL_MIN_PAYOUT),
            'cta_url' => $block?->settings['cta']['url'] ?? '/signup',
            'texts' => array_replace(array_fill_keys(config('app.supported_locales'), []), $block?->getTranslations('texts') ?? []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('admin/referrals.settings'))->schema([
                Toggle::make('enabled')->label(__('admin/referrals.enabled'))->helperText(__('admin/referrals.enabled_help'))->columnSpanFull(),
                TextInput::make('rate')->label(__('admin/referrals.rate'))->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')->required(),
                Select::make('commission')->label(__('admin/referrals.commission'))->required()->live()->options([
                    ReferralProgram::FIRST_PAYMENT => __('admin/referrals.first_payment'),
                    ReferralProgram::RECURRING => __('admin/referrals.recurring'),
                ]),
                TextInput::make('months')->label(__('admin/referrals.months'))->helperText(__('admin/referrals.months_help'))
                    ->numeric()->integer()->minValue(0)->required()
                    ->visible(fn (Get $get): bool => $get('commission') === ReferralProgram::RECURRING),
                TextInput::make('cookie_days')->label(__('admin/referrals.cookie_days'))->helperText(__('admin/referrals.cookie_days_help'))
                    ->numeric()->integer()->minValue(1)->maxValue(365)->required(),
                TextInput::make('min_payout')->label(__('admin/referrals.min_payout'))->helperText(__('admin/referrals.min_payout_help'))
                    ->numeric()->minValue(0)->suffix(strtoupper((string) config('services.stripe.currency')))->required(),
                TextInput::make('cta_url')->label(__('admin/referrals.cta_url'))->required()->maxLength(255),
            ])->columns(2),
            Section::make(__('admin/referrals.page_texts'))->description(__('admin/referrals.placeholders_help'))->schema([
                Tabs::make('languages')->tabs(array_map(fn (string $locale): Tab => $this->languageTab($locale), config('app.supported_locales'))),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')->footer([
                Actions::make([Action::make('save')->label(__('admin/common.save'))->submit('save')->keyBindings(['mod+s'])])->sticky()->key('form-actions'),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::put(Setting::REFERRAL_ENABLED, (bool) $state['enabled']);
        Setting::put(Setting::REFERRAL_RATE, (float) $state['rate']);
        Setting::put(Setting::REFERRAL_COMMISSION, $state['commission']);
        Setting::put(Setting::REFERRAL_COOKIE_DAYS, (int) $state['cookie_days']);
        Setting::put(Setting::REFERRAL_MIN_PAYOUT, (float) $state['min_payout']);

        // Hidden with first-payment commission; the last value is kept for when recurring is chosen again.
        if (isset($state['months'])) {
            Setting::put(Setting::REFERRAL_MONTHS, (int) $state['months']);
        }

        $block = HomeBlock::firstOrNew(['key' => ReferralProgram::BLOCK]);
        $texts = [];

        // A cleared text is left out, so that language falls back to English for it.
        foreach (config('app.supported_locales') as $locale) {
            $filled = $this->withoutBlanks($state['texts'][$locale] ?? []);

            if ($filled !== []) {
                $texts[$locale] = $filled;
            }
        }

        $block->replaceTranslations('texts', $texts);
        $block->settings = ['cta' => ['url' => $state['cta_url']]];
        $block->save();

        Notification::make()->title(__('admin/referrals.saved'))->success()->send();
    }

    private function languageTab(string $locale): Tab
    {
        $field = fn (string $path, string $label, bool $long = false) => Translations::field(
            "texts.{$locale}.{$path}",
            $label,
            $locale,
            required: Translations::isFallback($locale),
            long: $long,
        );
        $rows = fn (int $count, \Closure $fields): array => array_merge(...array_map($fields, range(0, $count - 1)));

        return Tab::make(Translations::localeName($locale))->schema([
            Fieldset::make(__('admin/referrals.hero'))->schema([
                $field('title', __('admin/referrals.title')),
                $field('cta.label', __('admin/referrals.cta')),
                $field('lead', __('admin/referrals.lead'), long: true)->columnSpanFull(),
            ])->columns(2),
            Fieldset::make(__('admin/referrals.steps'))->schema($rows(self::STEPS, fn (int $i): array => [
                $field("steps.{$i}.title", __('admin/referrals.step_title').($i + 1)),
                $field("steps.{$i}.text", __('admin/referrals.step_text').($i + 1)),
            ]))->columns(2),
            Fieldset::make(__('admin/referrals.benefits'))->schema($rows(self::BENEFITS, fn (int $i): array => [
                $field("benefits.{$i}.title", __('admin/referrals.benefit_title').($i + 1)),
                $field("benefits.{$i}.text", __('admin/referrals.benefit_text').($i + 1)),
            ]))->columns(2),
            Fieldset::make(__('admin/referrals.faq'))->schema([
                $field('earnings_heading', __('admin/referrals.earnings_heading'))->columnSpanFull(),
                ...$rows(self::QUESTIONS, fn (int $i): array => [
                    $field("faq.{$i}.question", __('admin/referrals.question').($i + 1)),
                    $field("faq.{$i}.answer", __('admin/referrals.answer').($i + 1), long: true),
                ]),
            ])->columns(2),
            Fieldset::make(__('admin/referrals.payout_method'))->schema([
                $field('payout_method', __('admin/referrals.payout_method'), long: true)->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $texts
     * @return array<array-key, mixed>
     */
    private function withoutBlanks(array $texts): array
    {
        foreach ($texts as $key => $value) {
            $value = is_array($value) ? $this->withoutBlanks($value) : $value;

            if ($value === null || $value === '' || $value === []) {
                unset($texts[$key]);
            } else {
                $texts[$key] = $value;
            }
        }

        return $texts;
    }
}
