<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SolutionResource;
use App\Filament\Support\Translations;
use App\Models\SolutionLabel;
use Filament\Actions\Action;
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
 * The words every solution page shares, per language: section titles, buttons and trigger names.
 * Each page's own content is edited on the page itself.
 */
class ManageSolutionLabels extends Page
{
    /** @var array<string, list<array{0: string, 1: string}>> group label => [path, label] */
    private static function texts(): array
    {
        return [
            __('admin/pricing.hero') => [
                ['hero.chip', __('admin/solutions.badge_name_is_the_page_name')], ['hero.get_started', __('admin/common.get_started_button')], ['hero.see_pricing', __('admin/solutions.pricing_link')],
                ['art.replied_in', __('admin/solutions.reply_speed_badge_on_the_image')],
            ],
            __('admin/solutions.automations') => [
                ['automations.title', __('admin/common.section_title')],
                ['trigger.comment', __('admin/common.comment')], ['trigger.dm', __('admin/common.dm')], ['trigger.story', __('admin/templates.trigger_story')],
                ['trigger.every_comment', __('admin/solutions.no_words_every_comment')], ['trigger.every_dm', __('admin/solutions.no_words_every_first_message')], ['trigger.every_story', __('admin/solutions.no_words_every_reply')],
                ['trigger.keyword', __('admin/solutions.keyword_singular')], ['trigger.keywords', __('admin/solutions.keywords_plural')], ['trigger.or', __('admin/solutions.or_between_words')],
            ],
            __('admin/solutions.how_it_works') => [
                ['steps.title', __('admin/common.section_title')],
            ],
            __('admin/solutions.faq_and_closing') => [
                ['faq.title_lead', __('admin/solutions.title_start')], ['faq.title_marked', __('admin/solutions.title_highlighted_word')], ['faq.title_end', __('admin/solutions.title_end')],
                ['closing.cta', __('admin/solutions.closing_button')],
            ],
        ];
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    public static function getNavigationLabel(): string
    {
        return __('admin/solutions.solution_page_template_texts');
    }

    public function getTitle(): string
    {
        return __('admin/solutions.solution_page_template_texts');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return SolutionResource::solutionsGroup();
    }

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'solution-labels';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'texts' => array_replace(array_fill_keys(config('app.supported_locales'), []), SolutionLabel::current()?->getTranslations('texts') ?? []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin/auth_screens.texts'))->description(__('admin/solutions.they_show_on_every_solution_page_around'))->schema([
                    Tabs::make('languages')->tabs(array_map(
                        fn (string $locale): Tab => Tab::make(Translations::localeName($locale))->schema(array_map(
                            fn (string $group, array $fields): Fieldset => Fieldset::make($group)->schema(array_map(
                                fn (array $field): TextInput => Translations::field(
                                    "texts.{$locale}.{$field[0]}",
                                    $field[1],
                                    $locale,
                                    required: Translations::isFallback($locale),
                                ),
                                $fields,
                            ))->columns(2),
                            array_keys(self::texts()),
                            self::texts(),
                        )),
                        config('app.supported_locales'),
                    )),
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

        $labels = SolutionLabel::current() ?? new SolutionLabel;
        $labels->replaceTranslations('texts', $texts);
        $labels->save();

        Notification::make()->title(__('admin/solutions.solution_page_template_texts_saved'))->success()->send();
    }
}
