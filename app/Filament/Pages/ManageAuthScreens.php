<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PlanResource;
use App\Filament\Support\Translations;
use App\Models\AuthScreen;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * The texts of the sign-up and sign-in screens (W01–W04), per language. Texts stored but not
 * shown here are kept as they are on save.
 */
class ManageAuthScreens extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    public static function getNavigationLabel(): string
    {
        return __('admin/auth_screens.nav');
    }

    public function getTitle(): string
    {
        return __('admin/auth_screens.title');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'auth-screens';

    /**
     * Each screen's label, and its texts: key => [label, 'long'?].
     *
     * @var array<string, array{label: string, texts: array<string, array{0: string, 1?: string}>}>
     */
    private static function screenDefinitions(): array
    {
        return [
            'signup' => ['label' => __('admin/auth_screens.signup'), 'texts' => [
                'title' => [__('admin/common.title')], 'lead' => [__('admin/common.description'), 'long'], 'google' => [__('admin/auth_screens.google')], 'submit' => [__('admin/auth_screens.submit')],
                'aside_text' => [__('admin/auth_screens.aside_text')], 'aside_link' => [__('admin/auth_screens.aside_link')],
                'legal' => [__('admin/auth_screens.legal'), 'long'], 'legal_terms' => [__('admin/auth_screens.legal_terms')], 'legal_privacy' => [__('admin/auth_screens.legal_privacy')],
            ]],
            'signin' => ['label' => __('admin/auth_screens.signin'), 'texts' => [
                'title' => [__('admin/common.title')], 'lead' => [__('admin/common.description'), 'long'], 'google' => [__('admin/auth_screens.google')], 'submit' => [__('admin/auth_screens.submit')],
                'aside_text' => [__('admin/auth_screens.aside_text')], 'aside_link' => [__('admin/auth_screens.aside_link')],
            ]],
            'email_form' => ['label' => __('admin/auth_screens.email_form'), 'texts' => [
                'or' => [__('admin/auth_screens.or')], 'email_label' => [__('admin/auth_screens.email_label')], 'email_placeholder' => [__('admin/auth_screens.email_placeholder')],
                'email_required' => [__('admin/auth_screens.email_required')], 'email_invalid' => [__('admin/auth_screens.email_invalid')],
                'notice_google_failed' => [__('admin/auth_screens.notice_google_failed'), 'long'], 'notice_signed_out' => [__('admin/auth_screens.notice_signed_out'), 'long'],
            ]],
            'check_email' => ['label' => __('admin/auth_screens.check_email'), 'texts' => [
                'title' => [__('admin/common.title')], 'lead_signup' => [__('admin/auth_screens.lead_signup'), 'long'], 'lead_signin' => [__('admin/auth_screens.lead_signin'), 'long'],
                'open_gmail' => [__('admin/auth_screens.open_gmail')], 'open_outlook' => [__('admin/auth_screens.open_outlook')],
                'resend_countdown' => [__('admin/auth_screens.resend_countdown'), 'long'], 'resend_prompt' => [__('admin/auth_screens.resend_prompt')], 'resend' => [__('admin/auth_screens.resend')],
                'resend_limited' => [__('admin/auth_screens.resend_limited')], 'wrong_address' => [__('admin/auth_screens.wrong_address')], 'use_another' => [__('admin/auth_screens.use_another')],
            ]],
            'verify' => ['label' => __('admin/auth_screens.verify'), 'texts' => [
                'title' => [__('admin/common.title')], 'lead' => [__('admin/common.description'), 'long'], 'status_title' => [__('admin/auth_screens.status_title')], 'status_note' => [__('admin/auth_screens.status_note')],
                'badge' => [__('admin/auth_screens.badge')], 'fine' => [__('admin/auth_screens.fine'), 'long'],
                'toast_created_title' => [__('admin/auth_screens.toast_created_title')], 'toast_created_text' => [__('admin/auth_screens.toast_created_text')], 'toast_welcome_back' => [__('admin/auth_screens.toast_welcome_back')],
            ]],
            'link_expired' => ['label' => __('admin/auth_screens.link_expired'), 'texts' => [
                'title' => [__('admin/common.title')], 'lead' => [__('admin/auth_screens.lead_email'), 'long'], 'submit' => [__('admin/auth_screens.send_new_link')], 'use_another' => [__('admin/auth_screens.use_another')],
                'toast_title' => [__('admin/auth_screens.toast_sent')], 'toast_text' => [__('admin/auth_screens.toast_text')],
            ]],
            'layout' => ['label' => __('admin/auth_screens.layout'), 'texts' => [
                'panel_line' => [__('admin/auth_screens.panel_line')], 'home_label' => [__('admin/auth_screens.home_label')], 'copyright' => [__('admin/auth_screens.copyright')],
                'privacy' => [__('admin/auth_screens.footer_privacy')], 'terms' => [__('admin/auth_screens.footer_terms')],
            ]],
        ];
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $locales = config('app.supported_locales');

        $this->form->fill($this->screens()->mapWithKeys(fn (AuthScreen $screen): array => [
            $screen->key => array_replace(array_fill_keys($locales, []), $screen->getTranslations('texts')),
        ])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('screens')->persistTabInQueryString()->tabs(array_map(
                    fn (string $key): Tab => $this->screenTab($key),
                    array_keys(self::screenDefinitions()),
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

        foreach ($this->screens() as $screen) {
            $texts = $screen->getTranslations('texts');

            // A cleared text is dropped from its language, so that language falls back for it.
            foreach (config('app.supported_locales') as $locale) {
                foreach ($state[$screen->key][$locale] ?? [] as $key => $text) {
                    if (blank($text)) {
                        unset($texts[$locale][$key]);
                    } else {
                        $texts[$locale][$key] = $text;
                    }
                }

                if (($texts[$locale] ?? []) === []) {
                    unset($texts[$locale]);
                }
            }

            $screen->replaceTranslations('texts', $texts);
            $screen->save();
        }

        Notification::make()->title(__('admin/auth_screens.saved'))->success()->send();
    }

    /**
     * @return Collection<int, AuthScreen>
     */
    private function screens(): Collection
    {
        return AuthScreen::query()->whereIn('key', array_keys(self::screenDefinitions()))->get();
    }

    private function screenTab(string $key): Tab
    {
        $languages = Tabs::make("{$key}-languages")->tabs(array_map(
            fn (string $locale): Tab => Tab::make(Translations::localeName($locale))->schema(array_map(
                fn (string $text, array $field): Field => Translations::field(
                    "{$key}.{$locale}.{$text}",
                    $field[0],
                    $locale,
                    required: Translations::isFallback($locale),
                    long: ($field[1] ?? null) === 'long',
                ),
                array_keys(self::screenDefinitions()[$key]['texts']),
                self::screenDefinitions()[$key]['texts'],
            ))->columns(2),
            config('app.supported_locales'),
        ));

        return Tab::make(self::screenDefinitions()[$key]['label'])->schema([
            Section::make(__('admin/auth_screens.texts'))->description(__('admin/auth_screens.texts_help'))->schema([$languages]),
        ]);
    }
}
