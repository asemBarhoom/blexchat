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

    protected static ?string $navigationLabel = 'نصوص شاشات التسجيل';

    protected static ?string $title = 'نصوص شاشات التسجيل وتسجيل الدخول';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'auth-screens';

    /**
     * Each screen's label, and its texts: key => [label, 'long'?].
     *
     * @var array<string, array{label: string, texts: array<string, array{0: string, 1?: string}>}>
     */
    private const SCREENS = [
        'signup' => ['label' => 'إنشاء حساب (W01)', 'texts' => [
            'title' => ['العنوان'], 'lead' => ['الوصف', 'long'], 'google' => ['زر Google'], 'submit' => ['زر البريد الإلكتروني'],
            'aside_text' => ['نص أعلى الصفحة'], 'aside_link' => ['رابط أعلى الصفحة'],
            'legal' => ['نص الموافقة ({terms} و{privacy} مكان الرابطين)', 'long'], 'legal_terms' => ['رابط الشروط'], 'legal_privacy' => ['رابط سياسة الخصوصية'],
        ]],
        'signin' => ['label' => 'تسجيل الدخول (W04)', 'texts' => [
            'title' => ['العنوان'], 'lead' => ['الوصف', 'long'], 'google' => ['زر Google'], 'submit' => ['زر البريد الإلكتروني'],
            'aside_text' => ['نص أعلى الصفحة'], 'aside_link' => ['رابط أعلى الصفحة'],
        ]],
        'email_form' => ['label' => 'نموذج البريد والتنبيهات', 'texts' => [
            'or' => ['الفاصل "أو"'], 'email_label' => ['عنوان الحقل'], 'email_placeholder' => ['النص التوضيحي للحقل'],
            'email_required' => ['خطأ: الحقل فارغ'], 'email_invalid' => ['خطأ: بريد غير مكتمل'],
            'notice_google_failed' => ['تنبيه: فشل الدخول عبر Google', 'long'], 'notice_signed_out' => ['تنبيه: تم تسجيل الخروج', 'long'],
        ]],
        'check_email' => ['label' => 'تحقّق من بريدك (W02)', 'texts' => [
            'title' => ['العنوان'], 'lead_signup' => ['الوصف عند إنشاء حساب ({email} مكان البريد)', 'long'], 'lead_signin' => ['الوصف عند تسجيل الدخول ({email} مكان البريد)', 'long'],
            'open_gmail' => ['زر Gmail'], 'open_outlook' => ['زر Outlook'],
            'resend_countdown' => ['نص العدّ التنازلي ({time} مكان الوقت)', 'long'], 'resend_prompt' => ['سؤال "لم يصلك؟"'], 'resend' => ['زر إعادة الإرسال'],
            'resend_limited' => ['خطأ: محاولات كثيرة'], 'wrong_address' => ['سؤال "عنوان خاطئ؟"'], 'use_another' => ['رابط بريد آخر'],
        ]],
        'verify' => ['label' => 'خطوة أخيرة (W03)', 'texts' => [
            'title' => ['العنوان'], 'lead' => ['الوصف', 'long'], 'status_title' => ['عنوان حالة التحقق'], 'status_note' => ['ملاحظة حالة التحقق'],
            'badge' => ['الشارة'], 'fine' => ['النص السفلي', 'long'],
            'toast_created_title' => ['إشعار: تم إنشاء الحساب'], 'toast_created_text' => ['نص إشعار إنشاء الحساب'], 'toast_welcome_back' => ['إشعار: أهلًا بعودتك ({name} مكان الاسم)'],
        ]],
        'link_expired' => ['label' => 'رابط منتهي الصلاحية', 'texts' => [
            'title' => ['العنوان'], 'lead' => ['الوصف ({email} مكان البريد)', 'long'], 'submit' => ['زر إرسال رابط جديد'], 'use_another' => ['رابط بريد آخر'],
            'toast_title' => ['إشعار: تم الإرسال'], 'toast_text' => ['نص الإشعار ({email} مكان البريد)'],
        ]],
        'layout' => ['label' => 'الإطار المشترك', 'texts' => [
            'panel_line' => ['عبارة اللوحة الجانبية'], 'home_label' => ['تسمية الشعار لقارئ الشاشة'], 'copyright' => ['حقوق النشر'],
            'privacy' => ['رابط الخصوصية في التذييل'], 'terms' => ['رابط الشروط في التذييل'],
        ]],
    ];

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
                    array_keys(self::SCREENS),
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

        Notification::make()->title('تم حفظ نصوص شاشات التسجيل')->success()->send();
    }

    /**
     * @return Collection<int, AuthScreen>
     */
    private function screens(): Collection
    {
        return AuthScreen::query()->whereIn('key', array_keys(self::SCREENS))->get();
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
                array_keys(self::SCREENS[$key]['texts']),
                self::SCREENS[$key]['texts'],
            ))->columns(2),
            config('app.supported_locales'),
        ));

        return Tab::make(self::SCREENS[$key]['label'])->schema([
            Section::make('النصوص')->description('العربية اختيارية: أي نص عربي فارغ يظهر بالإنجليزية. الكلمات بين القوسين { } تُستبدل تلقائيًا ويجب إبقاؤها كما هي.')->schema([$languages]),
        ]);
    }
}
