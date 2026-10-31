<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComparisonPointResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\ComparisonPoint;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComparisonPointResource extends Resource
{
    protected static ?string $model = ComparisonPoint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'نقاط قبل وبعد';

    protected static ?string $modelLabel = 'نقطة';

    protected static ?string $pluralModelLabel = 'نقاط قبل وبعد';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 17;

    /** @var array<string, string> */
    private const SIDE_NAMES = ['before' => 'قبل', 'after' => 'بعد'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('side')->label('الجهة')->options(self::SIDE_NAMES)->required(),
            Translations::input('text', 'النص'),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('side')->label('الجهة')->badge()->formatStateUsing(fn (string $state): string => self::SIDE_NAMES[$state] ?? $state),
            TextColumn::make('text')->label('النص')->searchable(),
        ], [
            SelectFilter::make('side')->label('الجهة')->options(self::SIDE_NAMES),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageComparisonPoints::route('/')];
    }
}
