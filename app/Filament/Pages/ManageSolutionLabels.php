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
    private const TEXTS = [
        'الواجهة' => [
            ['hero.chip', 'الشارة ({name} اسم الصفحة)'], ['hero.get_started', 'زر البدء'], ['hero.see_pricing', 'رابط الأسعار'],
            ['art.replied_in', 'شارة سرعة الرد في الصورة'],
        ],
        'الأتمتات' => [
            ['automations.title', 'عنوان القسم'],
            ['trigger.comment', 'تعليق'], ['trigger.dm', 'رسالة خاصة'], ['trigger.story', 'رد على ستوري'],
            ['trigger.every_comment', 'بلا كلمات: كل تعليق'], ['trigger.every_dm', 'بلا كلمات: كل رسالة أولى'], ['trigger.every_story', 'بلا كلمات: كل رد'],
            ['trigger.keyword', 'كلمة مفتاحية (مفرد)'], ['trigger.keywords', 'كلمات مفتاحية (جمع)'], ['trigger.or', 'أو (بين الكلمات)'],
        ],
        'كيف تعمل' => [
            ['steps.title', 'عنوان القسم'],
        ],
        'الأسئلة الشائعة والختام' => [
            ['faq.title_lead', 'العنوان: أوله'], ['faq.title_marked', 'العنوان: الكلمة المميّزة'], ['faq.title_end', 'العنوان: آخره'],
            ['closing.cta', 'زر الختام'],
        ],
    ];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationLabel = 'نصوص قالب صفحات الحلول';

    protected static ?string $title = 'نصوص قالب صفحات الحلول';

    protected static string|\UnitEnum|null $navigationGroup = SolutionResource::NAVIGATION_GROUP;

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
                Section::make('النصوص')->description('تظهر في كل صفحات الحلول حول محتوى الصفحة. العربية اختيارية: أي نص عربي فارغ يظهر بالإنجليزية.')->schema([
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
                            array_keys(self::TEXTS),
                            self::TEXTS,
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
                        Action::make('save')->label('حفظ')->submit('save')->keyBindings(['mod+s']),
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

        Notification::make()->title('تم حفظ نصوص قالب صفحات الحلول')->success()->send();
    }
}
