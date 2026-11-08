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

    protected static ?string $navigationLabel = 'نصوص وصور الأقسام';

    protected static ?string $title = 'نصوص وصور أقسام الصفحة الرئيسية';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

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
                    array_values(array_intersect(array_keys(self::BLOCK_LABELS), $this->blocks()->pluck('key')->all())),
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
                        Action::make('save')->label('حفظ')->submit('save')->keyBindings(['mod+s']),
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

        Notification::make()->title('تم حفظ محتوى الصفحة الرئيسية')->success()->send();
    }

    /** @var array<string, string> */
    private const BLOCK_LABELS = [
        'hero' => 'الواجهة (Hero)',
        'creators' => 'صُنّاع المحتوى',
        'intro' => 'المقدمة',
        'features' => 'الميزات',
        'autopilot' => 'Autopilot',
        'use-cases' => 'حالات الاستخدام',
        'before-after' => 'قبل وبعد',
        'see-it-in-action' => 'شاهده يعمل',
        'try-it' => 'جرّبه بنفسك',
        'get-started' => 'الخطوات',
        'numbers' => 'الأرقام',
        'faq' => 'الأسئلة الشائعة',
        'demo_replies' => 'ردود التجربة',
        'navigation' => 'القائمة العلوية',
        'footer' => 'التذييل',
        'not_found' => 'صفحة 404',
    ];

    /**
     * @return Collection<int, HomeBlock>
     */
    private function blocks(): Collection
    {
        return HomeBlock::query()->whereIn('key', array_keys(self::BLOCK_LABELS))->get();
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

        return Tab::make(self::BLOCK_LABELS[$key])->schema(array_filter([
            Section::make('النصوص')->description('العربية اختيارية: أي نص عربي فارغ يظهر بالإنجليزية.')->schema([$languages]),
            $shared === [] ? null : Section::make('الصور والروابط والأرقام (مشتركة بين اللغات)')->schema($shared)->columns(2),
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
            ->helperText('صفحة مثل /pricing أو قسم في الرئيسية مثل #try-it');
        $number = fn (string $path, string $label): TextInput => TextInput::make($at($path))->label($label)->numeric()->integer()->required();
        $each = fn (int $count, \Closure $fields): array => array_merge(...array_map($fields, range(0, $count - 1)));

        return match ($key) {
            'hero' => [[
                'العنوان الدوّار' => $each(3, fn (int $i): array => [["headline_rotations.{$i}.0", 'الجزء الأول '.($i + 1)], ["headline_rotations.{$i}.1", 'الكلمة المميّزة '.($i + 1)]]),
                'النصوص الرئيسية' => [['handwriting', 'الكلمة بخط اليد'], ['subtitle', 'العنوان الفرعي'], ['description', 'الوصف', 'long'], ['microcopy', 'نص تحت الزر'], ['cta.label', 'نص الزر']],
                'قصة الواجهة المتحركة' => [
                    ['story.pressure.counter', 'نص العدّاد'], ...$this->pluralTexts('story.pressure.counter', 'نص العدّاد'), ['story.pressure.more', 'نص "المزيد"'], ['story.working_badge', 'شارة "يعمل الآن"'], ['story.visitor_result', 'نتيجة تجربة الزائر'],
                    ...$each(3, fn (int $i): array => [["story.pressure.chips.{$i}.handle", 'حساب التعليق '.($i + 1)], ["story.pressure.chips.{$i}.text", 'التعليق '.($i + 1)]]),
                    ...$each(3, fn (int $i): array => [
                        ["story.relief.stats.{$i}.label", 'وصف الرقم '.($i + 1)], ["story.relief.stats.{$i}.suffix", 'لاحقة الرقم '.($i + 1), 'optional'],
                        ...$this->pluralTexts("story.relief.stats.{$i}.label", 'وصف الرقم '.($i + 1)),
                    ]),
                ],
                'مربع التجربة' => [
                    ['composer.label', 'العنوان'], ['composer.placeholder_prefix', 'بداية النص التوضيحي'], ['composer.placeholder', 'النص التوضيحي الثابت'], ['composer.sr_label', 'تسمية قارئ الشاشة'], ['composer.send_label', 'تسمية زر الإرسال'],
                    ...$each(3, fn (int $i): array => [["composer.examples.{$i}", 'مثال '.($i + 1)]]),
                ],
                'الدليل الاجتماعي والقنوات' => [
                    ['social_proof.rating', 'التقييم'], ['social_proof.count', 'عدد العملاء'], ['channels_label', 'عنوان القنوات'],
                    ...$each(4, fn (int $i): array => [["channels.{$i}.label", 'القناة '.($i + 1)]]),
                    ...$each(2, fn (int $i): array => [["partners.{$i}.label", 'الشريك '.($i + 1)]]),
                ],
                'المحادثات المتحركة' => $each(3, fn (int $i): array => [
                    ["conversations.{$i}.handle", 'الحساب '.($i + 1)], ["conversations.{$i}.initial", 'الحرف الأول '.($i + 1)],
                    ["conversations.{$i}.question", 'سؤال العميل '.($i + 1)], ["conversations.{$i}.reply", 'الرد '.($i + 1)],
                    ["conversations.{$i}.action", 'زر الرد '.($i + 1)], ["conversations.{$i}.result", 'النتيجة '.($i + 1), 'optional'],
                ]),
            ], [
                $image('photos.relax', 'الصورة الأساسية (الهدوء)'), $image('photos.celebrate', 'صورة الاحتفال'),
                $image('cutout', 'صورة الشخص المقصوصة'),
                $url('cta.url', 'رابط الزر'), $number('story.pressure.count', 'رقم العدّاد'),
                ...array_map(fn (int $i): TextInput => $number("story.relief.stats.{$i}.to", 'الرقم '.($i + 1)), range(0, 2)),
                FileUpload::make($at('social_proof.avatars'))->label('صور العملاء')->image()->multiple()->reorderable()
                    ->disk('public')->directory('marketing/home/uploads')->visibility('public')->columnSpanFull(),
                ...array_map(fn (int $i): FileUpload => $image("conversations.{$i}.avatar", 'صورة المحادثة '.($i + 1)), range(0, 2)),
            ]],
            'creators' => [['العنوان' => [['label', 'العنوان']]]],
            'intro' => [['المقدمة' => [['headline.0', 'السطر الأول'], ['headline.1', 'السطر الثاني'], ['lead', 'الوصف', 'long']]]],
            'features' => [[
                'عام' => [['slides_label', 'تسمية قارئ الشاشة']],
                '01 — التعليقات' => [
                    ...$this->slideTexts(0),
                    ['slides.0.scene.post', 'نص المنشور'], ['slides.0.scene.reply', 'الرد'], ['slides.0.scene.action', 'زر الرد'], ['slides.0.scene.order', 'الطلب الناتج'],
                    ...$each(3, fn (int $i): array => [["slides.0.scene.comments.{$i}.handle", 'حساب التعليق '.($i + 1)], ["slides.0.scene.comments.{$i}.text", 'التعليق '.($i + 1)]]),
                ],
                '02 — الرسائل' => [
                    ...$this->slideTexts(1),
                    ['slides.1.scene.handle', 'الحساب'], ['slides.1.scene.question', 'السؤال'], ['slides.1.scene.reply', 'الرد'], ['slides.1.scene.action', 'زر الرد'], ['slides.1.scene.seen', 'نص "شوهد"'],
                    ['slides.1.scene.lead.tag', 'شارة العميل المحتمل'], ['slides.1.scene.lead.name', 'اسم العميل'], ['slides.1.scene.lead.detail', 'بيانات العميل'],
                ],
                '03 — الستوري' => [
                    ...$this->slideTexts(2),
                    ['slides.2.scene.story', 'نص الستوري'], ['slides.2.scene.handle', 'الحساب'], ['slides.2.scene.reaction', 'التفاعل'], ['slides.2.scene.reply', 'الرد'], ['slides.2.scene.action', 'زر الرد'],
                ],
                '04 — القنوات' => [
                    ...$this->slideTexts(3),
                    ['slides.3.scene.inbox', 'عنوان الصندوق'], ['slides.3.scene.total', 'الإجمالي'], ['slides.3.scene.total_label', 'وصف الإجمالي'], ['slides.3.scene.footer', 'النص السفلي'],
                    ...$each(4, fn (int $i): array => [["slides.3.scene.tiles.{$i}.count", 'عدد القناة '.($i + 1)]]),
                ],
            ], [
                ...array_map(fn (int $i): TextInput => $url("slides.{$i}.cta.url", 'رابط زر الشريحة 0'.($i + 1)), range(0, 3)),
                $image('slides.0.scene.post_photo', 'صورة منشور الشريحة 01'),
                $image('slides.1.scene.avatar', 'صورة الشريحة 02'),
                $image('slides.2.scene.photo', 'صورة ستوري الشريحة 03'),
            ]],
            'autopilot' => [['الكلمة الكبيرة' => [['kicker', 'النص الصغير'], ['mark', 'العلامة'], ['word.0', 'الجزء الأول'], ['word.1', 'الجزء الثاني']]], [
                $image('photo', 'الصورة'),
            ]],
            'use-cases' => [['حالات الاستخدام' => [['heading', 'العنوان'], ['cta_label', 'نص زر البطاقات'], ['prev_label', 'تسمية سهم السابق'], ['next_label', 'تسمية سهم التالي']]]],
            'before-after' => [['قبل وبعد' => [['heading', 'العنوان'], ['before.title', 'عنوان "قبل"'], ['after.title', 'عنوان "بعد"'], ['cta.label', 'نص الزر']]], [
                $url('cta.url', 'رابط الزر'),
            ]],
            'see-it-in-action' => [[
                'عام' => [['heading', 'العنوان'], ['lead', 'الوصف'], ['composer_placeholder', 'نص مربع الرسالة'], ['tabs_label', 'تسمية قارئ الشاشة']],
                'ترحيب بالمتابعين الجدد' => [...$this->flowTexts(0), ['tabs.0.scene.event', 'الحدث'], ['tabs.0.scene.result', 'النتيجة']],
                'رسائل الترحيب' => [...$this->flowTexts(1), ['tabs.1.scene.handle', 'الحساب'], ['tabs.1.scene.question', 'السؤال'], ['tabs.1.scene.lead.tag', 'شارة العميل'], ['tabs.1.scene.lead.name', 'اسم العميل'], ['tabs.1.scene.lead.detail', 'بيانات العميل']],
                'الأسئلة الشائعة التلقائية' => [...$this->flowTexts(2), ['tabs.2.scene.event', 'الحدث'], ['tabs.2.scene.handle', 'الحساب'], ['tabs.2.scene.question', 'السؤال'], ['tabs.2.scene.result', 'النتيجة']],
                'رسالة من تعليق' => [...$this->flowTexts(3), ['tabs.3.scene.handle', 'الحساب'], ['tabs.3.scene.question', 'التعليق'], ['tabs.3.scene.transition', 'نص الانتقال'], ['tabs.3.scene.result', 'النتيجة']],
                'المسابقات' => [...$this->flowTexts(4), ['tabs.4.scene.handle', 'الحساب'], ['tabs.4.scene.question', 'التعليق'], ['tabs.4.scene.counter.value', 'العدد'], ['tabs.4.scene.counter.label', 'وصف العدد']],
            ], array_map(fn (int $i): FileUpload => $image("tabs.{$i}.scene.avatar", 'صورة التبويب '.($i + 1)), range(0, 3))],
            'try-it' => [[
                'جرّبه بنفسك' => [
                    ['kicker', 'النص الصغير'], ['heading_lead', 'العنوان'], ['heading_mark', 'الكلمة المميّزة'], ['lead', 'الوصف'], ['placeholder', 'النص التوضيحي'],
                    ['bridge', 'نص بعد الرد'], ['handle', 'اسم الزائر'], ['initial', 'الحرف الأول'], ['cta.label', 'نص الزر'],
                    ...$each(3, fn (int $i): array => [["suggestions.{$i}", 'اقتراح '.($i + 1)]]),
                ],
            ], [
                $url('cta.url', 'رابط الزر'),
            ]],
            'get-started' => [[
                'الخطوات' => [
                    ['heading', 'العنوان'], ['cta.label', 'نص الزر الرئيسي'], ['secondary_cta.label', 'نص الزر الثانوي'],
                    ...$each(3, fn (int $i): array => [["steps.{$i}.number", 'رقم الخطوة '.($i + 1)], ["steps.{$i}.name", 'اسم الخطوة '.($i + 1)], ["steps.{$i}.line", 'وصف الخطوة '.($i + 1)]]),
                ],
            ], [
                $url('cta.url', 'رابط الزر الرئيسي'), $url('secondary_cta.url', 'رابط الزر الثانوي'),
            ]],
            'numbers' => [['الأرقام' => [['heading', 'العنوان']]]],
            'faq' => [['الأسئلة الشائعة' => [['heading', 'العنوان']]]],
            'demo_replies' => [[
                'بطاقة الرد' => [['card.label', 'عنوان البطاقة'], ['card.timer', 'زمن الرد']],
                'الرد الافتراضي (عندما لا تطابق أي قاعدة)' => [['fallback.reply', 'الرد'], ['fallback.action', 'زر الرد'], ['fallback.result', 'النتيجة']],
            ]],
            'navigation' => [[
                'الشريط' => [
                    ...$each(4, fn (int $i): array => [["items.{$i}.label", 'الرابط '.($i + 1)]]),
                    ['sign_in.label', 'زر تسجيل الدخول'], ['get_started.label', 'زر البدء'], ['get_started_mobile.label', 'زر البدء على الجوال'],
                    ['aria.main', 'تسمية قارئ الشاشة للشريط'], ['aria.mobile', 'تسمية قارئ الشاشة على الجوال'], ['aria.open_menu', 'تسمية فتح القائمة'], ['aria.close_menu', 'تسمية إغلاق القائمة'],
                ],
                'قائمة المنتج' => [
                    ['menus.product.heading', 'العنوان'],
                    ...$each(5, fn (int $i): array => [["menus.product.items.{$i}.title", 'العنصر '.($i + 1)], ["menus.product.items.{$i}.desc", 'وصف العنصر '.($i + 1)]]),
                    ['menus.product.items.4.cta', 'نص زر العنصر 5'],
                ],
                'قائمة الحلول' => [
                    ['menus.solutions.heading', 'العنوان'],
                    ...$each(8, fn (int $i): array => [["menus.solutions.items.{$i}.title", 'الحل '.($i + 1)], ["menus.solutions.items.{$i}.desc", 'وصف الحل '.($i + 1)]]),
                ],
            ], [
                $url('items.2.url', 'رابط الرابط 3'), $url('items.3.url', 'رابط الرابط 4'),
                $url('sign_in.url', 'رابط تسجيل الدخول'), $url('get_started.url', 'رابط زر البدء'), $url('get_started_mobile.url', 'رابط زر البدء على الجوال'),
                ...array_map(fn (int $i): TextInput => $url("menus.product.items.{$i}.url", 'رابط عنصر المنتج '.($i + 1)), range(0, 4)),
                ...array_map(fn (int $i): TextInput => $url("menus.solutions.items.{$i}.url", 'رابط الحل '.($i + 1)), range(0, 7)),
            ]],
            'footer' => [[
                'عام' => [
                    ['tagline', 'الشعار'], ['cta.label', 'نص الزر'], ['copyright', 'حقوق النشر'],
                    ['made_for', 'عبارة "صُمّم من أجل"'], ['made_for_sr', 'العبارة كاملة لقارئ الشاشة'],
                    ...$each(4, fn (int $i): array => [["giant_words.{$i}", 'الكلمة الكبيرة '.($i + 1)]]),
                    ['legal.0.label', 'رابط الخصوصية'], ['legal.1.label', 'رابط الشروط'],
                    ['aria.footer', 'تسمية قارئ الشاشة للتذييل'], ['aria.home', 'تسمية رابط الرئيسية'],
                ],
                ...collect([[0, 'عمود المنتج', 3], [1, 'عمود الحلول', 4], [2, 'عمود الحساب', 2]])->mapWithKeys(fn (array $column): array => [$column[1] => [
                    ["columns.{$column[0]}.title", 'العنوان'],
                    ...$each($column[2], fn (int $i): array => [["columns.{$column[0]}.links.{$i}.label", 'الرابط '.($i + 1)]]),
                ]])->all(),
            ], [
                $url('cta.url', 'رابط الزر'), $url('legal.0.url', 'رابط صفحة الخصوصية'), $url('legal.1.url', 'رابط صفحة الشروط'),
                ...collect([[0, 3], [1, 4], [2, 2]])->flatMap(fn (array $column): array => array_map(
                    fn (int $i): TextInput => $url("columns.{$column[0]}.links.{$i}.url", 'رابط العمود '.($column[0] + 1).' — '.($i + 1)),
                    range(0, $column[1] - 1),
                ))->all(),
            ]],
            'not_found' => [[
                'التعليق والرد' => [['question', 'التعليق'], ['replied', 'زمن الرد'], ['answer', 'الرد', 'long']],
                'الصفحة' => [['title', 'العنوان'], ['body', 'النص', 'long'], ['cta.label', 'نص الزر']],
            ], [
                TextInput::make($at('code'))->label('الرمز')->required()->maxLength(10),
                $url('cta.url', 'رابط الزر'),
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
            ["slides.{$slide}.index", 'الرقم'], ["slides.{$slide}.headline", 'العنوان'], ["slides.{$slide}.body", 'النص', 'long'],
            ["slides.{$slide}.cta.label", 'نص الزر'], ["slides.{$slide}.phone_label", 'عنوان شاشة الجوال'],
        ];
    }

    /**
     * @return list<array<int, string>>
     */
    private function flowTexts(int $tab): array
    {
        return [
            ["tabs.{$tab}.title", 'العنوان'], ["tabs.{$tab}.description", 'الوصف'], ["tabs.{$tab}.surface", 'المنصة'],
            ["tabs.{$tab}.sr", 'وصف قارئ الشاشة'], ["tabs.{$tab}.scene.reply", 'الرد'], ["tabs.{$tab}.scene.action", 'زر الرد'],
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
