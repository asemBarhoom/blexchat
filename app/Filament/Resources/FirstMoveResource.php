<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirstMoveResource\Pages;
use App\Filament\Support\Translations;
use App\Models\FirstMove;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FirstMoveResource extends Resource
{
    /** @var array<string, string> */
    public const TRIGGER_LABELS = ['comment' => 'تعليق على منشور', 'story' => 'رد على قصة', 'dm' => 'رسالة خاصة'];

    protected static ?string $model = FirstMove::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'الخطوات الأولى';

    protected static ?string $modelLabel = 'خطوة أولى';

    protected static ?string $pluralModelLabel = 'الخطوات الأولى';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('المعرّف')->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText('ثابت يستخدمه الفرونت، مثل comment-dm.'),
            Select::make('trigger')->label('المشغّل')->options(self::TRIGGER_LABELS)->required()->native(false)
                ->helperText('الخطوة التي يبدأ منها معالج الأتمتة.'),
            TextInput::make('template')->label('قالب المعالج')->alphaDash()->maxLength(50)
                ->helperText('اختياري: مفتاح قالب يفتح به المعالج.'),
            Translations::input('title', 'العنوان'),
            Translations::input('line', 'الوصف', long: true),

            Section::make('مثال المحادثة')->schema([
                TextInput::make('incoming_who')->label('اسم المرسل (مثال)')->required()->maxLength(50)->extraInputAttributes(['dir' => 'ltr']),
                Translations::input('incoming_kind', 'نوع الرسالة'),
                Translations::input('incoming_text', 'الرسالة الواردة'),
                Translations::input('reply', 'الرد التلقائي', long: true),
            ]),

            Toggle::make('active')->label('معروضة')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('العنوان')->searchable(),
                TextColumn::make('key')->label('المعرّف')->badge()->color('gray'),
                TextColumn::make('trigger')->label('المشغّل')->formatStateUsing(fn (string $state): string => self::TRIGGER_LABELS[$state] ?? $state),
                TextColumn::make('template')->label('القالب')->placeholder('—'),
                ToggleColumn::make('active')->label('معروضة'),
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
        return ['index' => Pages\ManageFirstMoves::route('/')];
    }
}
