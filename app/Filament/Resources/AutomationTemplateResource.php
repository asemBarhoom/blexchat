<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutomationTemplateResource\Pages;
use App\Filament\Support\Translations;
use App\Models\AutomationTemplate;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AutomationTemplateResource extends Resource
{
    public const BUILDS = ['quick' => 'أتمتة سريعة', 'flow' => 'منشئ التدفقات'];

    public const GOALS = ['sell' => 'بيع منتج', 'grow' => 'تنمية القائمة', 'support' => 'الرد على الأسئلة', 'book' => 'استقبال الحجوزات'];

    public const TRIGGERS = ['comment' => 'تعليق على منشور أو ريل', 'story' => 'رد على ستوري', 'dm' => 'رسالة خاصة', 'ad' => 'إعلان المراسلة'];

    public const MATCHES = ['words' => 'كلمات محددة', 'any' => 'أي تعليق أو رسالة'];

    protected static ?string $model = AutomationTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'قوالب الأتمتة';

    protected static ?string $modelLabel = 'قالب أتمتة';

    protected static ?string $pluralModelLabel = 'قوالب الأتمتة';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المحتوى';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $fillsWizard = fn (Get $get): bool => $get('build') === 'quick' && $get('trigger') !== 'ad';

        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('key')->label('المعرّف')->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText('ثابت يستخدمه الفرونت في الرابط، مثل t1.'),
                Toggle::make('is_starter')->label('بداية المعالج لهذا المحفّز')->live()
                    ->helperText('لا يظهر في قائمة القوالب: يبدأ به المعالج حين لا يُختار قالب. واحد لكل محفّز.')
                    ->rules([fn (Get $get, ?AutomationTemplate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if ($value && AutomationTemplate::starters()->where('trigger', $get('trigger'))->whereKeyNot($record?->id)->exists()) {
                            $fail('لهذا المحفّز بداية أخرى بالفعل.');
                        }
                    }]),
            ])->columnSpanFull(),
            Translations::input('name', 'الاسم'),
            Translations::input('line', 'الوصف', required: false, long: true),

            Grid::make(4)->schema([
                Select::make('trigger')->label('المحفّز')->options(self::TRIGGERS)->required()->live(),
                Select::make('build')->label('النوع')->options(self::BUILDS)->required()->default('quick')->live(),
                Select::make('goal')->label('الهدف')->options(self::GOALS)
                    ->required(fn (Get $get): bool => ! $get('is_starter')),
                Toggle::make('locked')->label('يحتاج خطة مدفوعة')->inline(false),
            ])->columnSpanFull(),

            Section::make('ما يملؤه في المعالج')->visible($fillsWizard)->schema([
                Select::make('match')->label('الرد على')->options(self::MATCHES)->required($fillsWizard)->live(),
                Grid::make(count(config('app.supported_locales')))->visible(fn (Get $get): bool => $get('match') === 'words')->schema(array_map(
                    fn (string $locale): TagsInput => TagsInput::make("keywords.{$locale}")
                        ->label('الكلمات — '.Translations::localeName($locale))
                        ->placeholder('اكتب كلمة واضغط Enter'),
                    config('app.supported_locales'),
                ))->columnSpanFull(),
                Translations::input('public_reply', 'الرد العلني تحت التعليق', required: false)
                    ->visible(fn (Get $get): bool => $get('trigger') === 'comment'),
                Translations::input('dm_text', 'الرسالة الخاصة', required: true, long: true),
                Translations::input('dm_button', 'نص الزر', required: false),
                TextInput::make('link_url')->label('رابط الزر (مثال)')->url()->maxLength(2048)->columnSpanFull(),
            ])->columnSpanFull(),

            Toggle::make('active')->label('معروض')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable()->wrap(),
                TextColumn::make('key')->label('المعرّف')->badge()->color('gray'),
                TextColumn::make('trigger')->label('المحفّز')->formatStateUsing(fn (string $state): string => self::TRIGGERS[$state] ?? $state),
                TextColumn::make('build')->label('النوع')->badge()->formatStateUsing(fn (string $state): string => self::BUILDS[$state] ?? $state),
                TextColumn::make('goal')->label('الهدف')->formatStateUsing(fn (?string $state): string => self::GOALS[$state] ?? '—'),
                IconColumn::make('is_starter')->label('بداية المعالج')->boolean(),
                IconColumn::make('locked')->label('مدفوع')->boolean(),
                ToggleColumn::make('active')->label('معروض'),
            ])
            ->filters([
                SelectFilter::make('trigger')->label('المحفّز')->options(self::TRIGGERS),
                TernaryFilter::make('is_starter')->label('بداية المعالج'),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                // The wizard opens with a starter whenever no template is named.
                DeleteAction::make()->label('حذف')->hidden(fn (AutomationTemplate $record): bool => $record->is_starter),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAutomationTemplates::route('/')];
    }
}
