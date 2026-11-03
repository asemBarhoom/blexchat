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
    public const NAVIGATION_GROUP = 'صفحة الأسعار';

    public const FIGURES_HELP = 'تُملأ تلقائياً من الخطط: {max_saving} أكبر نسبة توفير سنوي، {free_contacts} حد جهات الاتصال في الخطة المجانية، {trial_days} أيام التجربة، {trial_plan} اسم الخطة التي فيها تجربة.';

    /** @var array<string, list<array{0: string, 1: string, 2?: string}>> group label => [path, label, 'long'] */
    private const TEXTS = [
        'الواجهة' => [
            ['hero.chip', 'الشارة'], ['hero.title', 'العنوان'], ['hero.lead', 'المقدمة', 'long'], ['hero.billing_label', 'تسمية مدة الفوترة (لقارئ الشاشة)'],
            ['hero.monthly', 'شهرياً'], ['hero.annual', 'سنوياً'], ['hero.save', 'التوفير'], ['hero.note', 'ملاحظة تحت البطاقات'],
        ],
        'بطاقات الخطط' => [
            ['card.ribbon', 'شريط الخطة المميزة'], ['card.forever', 'بجانب سعر المجانية'], ['card.per_month', 'بجانب السعر'], ['card.no_card', 'تحت سعر المجانية'],
            ['card.billed_annually', 'الفوترة السنوية ({total})'], ['card.billed_monthly', 'الفوترة الشهرية'], ['card.start_free', 'زر المجانية'],
            ['card.try_free', 'زر التجربة ({days})'], ['card.choose', 'زر الاختيار ({plan})'], ['card.contacts', 'جهات الاتصال ({contacts})'], ['card.ai', 'شارة الذكاء الاصطناعي'],
        ],
        'جدول المقارنة' => [
            ['compare.title', 'العنوان'], ['compare.feature', 'رأس عمود الميزات (لقارئ الشاشة)'], ['compare.included', 'معنى علامة الصح (لقارئ الشاشة)'], ['compare.not_included', 'معنى الشرطة (لقارئ الشاشة)'],
        ],
        'قسم الوكالات' => [
            ['agency.title', 'العنوان'], ['agency.line', 'السطر', 'long'], ['agency.link_label', 'نص الرابط'],
        ],
        'الأسئلة الشائعة' => [
            ['faq.title', 'العنوان'], ['faq.marked', 'الكلمة المميّزة من العنوان'],
        ],
    ];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'نصوص صفحة الأسعار';

    protected static ?string $title = 'نصوص صفحة الأسعار';

    protected static string|\UnitEnum|null $navigationGroup = self::NAVIGATION_GROUP;

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
                Section::make('النصوص')->description('العربية اختيارية: أي نص عربي فارغ يظهر بالإنجليزية. '.self::FIGURES_HELP.' أما {total} و{days} و{plan} و{contacts} في البطاقات فيملؤها الموقع لكل خطة.')->schema([
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
                            array_keys(self::TEXTS),
                            self::TEXTS,
                        )),
                        config('app.supported_locales'),
                    )),
                ]),
                Section::make('الروابط (مشتركة بين اللغات)')->schema([
                    TextInput::make('agency_url')->label('رابط قسم الوكالات')->required()->helperText('صفحة مثل /solutions/agencies'),
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

        $page = PricingPage::current() ?? new PricingPage;
        $page->replaceTranslations('texts', $texts);
        $page->agency_url = $state['agency_url'];
        $page->save();

        Notification::make()->title('تم حفظ نصوص صفحة الأسعار')->success()->send();
    }
}
