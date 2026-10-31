<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeStatResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\HomeStat;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomeStatResource extends Resource
{
    protected static ?string $model = HomeStat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'الأرقام';

    protected static ?string $modelLabel = 'رقم';

    protected static ?string $pluralModelLabel = 'الأرقام';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translations::input('value', 'القيمة'),
            Translations::input('label', 'الوصف'),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('value')->label('القيمة'),
            TextColumn::make('label')->label('الوصف'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHomeStats::route('/')];
    }
}
