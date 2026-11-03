<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolutionResource\Pages;
use App\Filament\Support\Translations;
use App\Models\Solution;
use App\Models\SolutionAutomation;
use App\Models\UseCase;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SolutionResource extends Resource
{
    public const NAVIGATION_GROUP = 'صفحات الحلول';

    /** @var array<string, string> */
    public const SOURCE_LABELS = ['comment' => 'تعليق', 'dm' => 'رسالة خاصة', 'story' => 'رد على ستوري'];

    protected static ?string $model = Solution::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationLabel = 'صفحات الحلول';

    protected static ?string $modelLabel = 'صفحة حل';

    protected static ?string $pluralModelLabel = 'صفحات الحلول';

    protected static string|\UnitEnum|null $navigationGroup = self::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الصفحة')->schema([
                Select::make('use_case_id')->label('بطاقة حالة الاستخدام')
                    ->options(fn (): array => UseCase::query()->orderBy('sort')->get()
                        ->mapWithKeys(fn (UseCase $useCase): array => [$useCase->id => "{$useCase->name} — /solutions/{$useCase->slug}"])->all())
                    ->unique(ignoreRecord: true)
                    ->live()
                    ->helperText('الصفحة المرتبطة ببطاقة في الصفحة الرئيسية تأخذ منها الرابط والاسم والشكل واللون. اتركه فارغاً لصفحة بلا بطاقة.'),

                Group::make([
                    TextInput::make('slug')->label('الرابط (/solutions/…)')->required()->alphaDash()->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->notIn(fn (): array => UseCase::pluck('slug')->all())
                        ->validationMessages(['not_in' => 'لهذا الرابط بطاقة حالة استخدام؛ اربط الصفحة بها بدلاً من ذلك.']),
                    Translations::input('name', 'الاسم'),
                    Select::make('shape')->label('الشكل')->options(array_combine(UseCase::SHAPES, UseCase::SHAPES))->required(),
                    Select::make('tone')->label('لون الخلفية')->options(array_combine(Solution::TONES, Solution::TONES))->required(),
                ])->columns(2)->columnSpanFull()
                    ->visible(fn (Get $get): bool => blank($get('use_case_id'))),

                Translations::input('headline', 'العنوان الرئيسي'),
                Translations::input('supporting', 'النص التعريفي', long: true),
                Translations::input('closing', 'الجملة الختامية'),
                Toggle::make('active')->label('ظاهرة في الموقع')->default(true),
            ])->columns(2),

            Section::make('النشاط التجريبي')->description('الحساب الذي تجري عليه المحادثات في المعاينة.')->schema([
                TextInput::make('sample_handle')->label('اسم الحساب')->required()->maxLength(50)->prefix('@'),
                self::avatar('sample_avatar'),
                ColorPicker::make('avatar_color')->label('لون الصورة الرمزية')->required()->default('#0b0b0f'),
                ColorPicker::make('avatar_text_color')->label('لون الحرف')->required()->default('#ffffff'),
            ])->columns(2),

            Section::make('الأتمتات الثلاث')->schema([
                Repeater::make('automations')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->minItems(3)
                    ->maxItems(3)
                    ->defaultItems(3)
                    ->addActionLabel('إضافة أتمتة')
                    ->itemLabel(fn (array $state): ?string => $state['title']['ar'] ?? $state['title']['en'] ?? null)
                    ->collapsible()
                    ->schema([
                        Translations::input('title', 'العنوان'),
                        Translations::input('line', 'الوصف', long: true),
                        Select::make('trigger_source')->label('المُشغّل')->options(self::SOURCE_LABELS)->required()->default('comment'),
                        TagsInput::make('trigger_words')->label('الكلمات المفتاحية')
                            ->placeholder('اكتب كلمة واضغط Enter')
                            ->helperText('فارغ = كل تعليق أو رسالة أو رد. إن وُجدت كلمات فيجب أن تحتويها رسالة العميل.'),
                        Fieldset::make('المحادثة')->schema([
                            Textarea::make('customer')->label('رسالة العميل')->rows(2)->required(),
                            Textarea::make('reply')->label('الرد الآلي')->rows(2)->required(),
                            TextInput::make('button')->label('نص الزر')->required()->maxLength(SolutionAutomation::BUTTON_MAX),
                            TextInput::make('link')->label('الرابط')->required()->maxLength(255)
                                ->extraInputAttributes(['dir' => 'ltr']),
                        ])->columns(2),
                        Fieldset::make('المنشور أو الستوري (اختياري)')->schema([
                            FileUpload::make('post_image')->label('صورة')->image()->disk('public')->directory(Solution::UPLOADS)->visibility('public')
                                ->helperText('أو اكتب نص بطاقة بدلاً من الصورة.'),
                            TextInput::make('post_tile_text')->label('نص البطاقة'),
                            Select::make('post_tile_tone')->label('لون البطاقة')
                                ->options(array_combine(SolutionAutomation::TILE_TONES, SolutionAutomation::TILE_TONES))
                                ->requiredWith('post_tile_text'),
                            TextInput::make('post_likes')->label('الإعجابات')->maxLength(20),
                            Textarea::make('post_caption')->label('الوصف')->rows(2)->columnSpanFull(),
                        ])->columns(2),
                        Fieldset::make('حساب آخر (اختياري)')->schema([
                            TextInput::make('account_handle')->label('اسم الحساب')->maxLength(50)->prefix('@')
                                ->helperText('لعملاء الوكالة مثلاً. فارغ = حساب الصفحة.'),
                            self::avatar('account_avatar'),
                            ColorPicker::make('account_color')->label('لون الصورة الرمزية'),
                            ColorPicker::make('account_text_color')->label('لون الحرف'),
                        ])->columns(2),
                    ]),
            ]),

            Section::make('كيف تعمل')->schema([
                Repeater::make('steps')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->minItems(1)
                    ->addActionLabel('إضافة خطوة')
                    ->schema([Translations::input('text', 'الخطوة')]),
            ]),

            Section::make('الأسئلة الشائعة')->schema([
                Repeater::make('faqItems')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->addActionLabel('إضافة سؤال')
                    ->itemLabel(fn (array $state): ?string => $state['question']['ar'] ?? $state['question']['en'] ?? null)
                    ->collapsible()
                    ->schema([
                        Translations::input('question', 'السؤال'),
                        Translations::input('answer', 'الإجابة', long: true),
                    ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('useCase')->withCount('automations'))
            ->columns([
                TextColumn::make('page_name')->label('الاسم')
                    ->state(fn (Solution $record): ?string => $record->identity()->name),
                TextColumn::make('page_slug')->label('الرابط')->badge()->color('gray')
                    ->state(fn (Solution $record): ?string => '/solutions/'.$record->identity()->slug),
                TextColumn::make('headline')->label('العنوان')->wrap()->searchable(),
                TextColumn::make('useCase.name')->label('البطاقة')->placeholder('بلا بطاقة'),
                ToggleColumn::make('active')->label('ظاهرة'),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolutions::route('/'),
            'create' => Pages\CreateSolution::route('/create'),
            'edit' => Pages\EditSolution::route('/{record}/edit'),
        ];
    }

    private static function avatar(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->label('صورة الحساب (اختياري)')
            ->helperText('بدونها يُرسم أول حرف من اسم الحساب على اللون.')
            ->image()
            ->avatar()
            ->disk('public')
            ->directory(Solution::UPLOADS)
            ->visibility('public');
    }
}
