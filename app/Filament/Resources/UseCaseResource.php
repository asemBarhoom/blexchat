<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UseCaseResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\UseCase;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UseCaseResource extends Resource
{
    protected static ?string $model = UseCase::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'حالات الاستخدام';

    protected static ?string $modelLabel = 'حالة استخدام';

    protected static ?string $pluralModelLabel = 'حالات الاستخدام';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('المعرّف')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('slug')->label('رابط صفحة الحل (/solutions/…)')->required()->alphaDash()->unique(ignoreRecord: true),
            Translations::input('name', 'الاسم'),
            Translations::input('line', 'الوصف'),
            Select::make('shape')->label('شكل البطاقة')->options(array_combine(UseCase::SHAPES, UseCase::SHAPES))->required(),
            Select::make('tone')->label('لون البطاقة')->options(array_combine(UseCase::TONES, UseCase::TONES))->required(),
            HomeContentFields::active(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('name')->label('الاسم')->searchable(),
            TextColumn::make('line')->label('الوصف'),
            TextColumn::make('slug')->label('الرابط'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUseCases::route('/')];
    }
}
