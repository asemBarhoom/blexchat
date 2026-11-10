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

    public static function getNavigationLabel(): string
    {
        return __('admin/home.before_and_after_points');
    }

    public static function getModelLabel(): string
    {
        return __('admin/home.point');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/home.before_and_after_points');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 17;

    /** @return array<string, string> */
    private static function sideNames(): array
    {
        return ['before' => __('admin/home.before'), 'after' => __('admin/home.after')];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('side')->label(__('admin/home.side'))->options(self::sideNames())->required(),
            Translations::input('text', __('admin/common.text')),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('side')->label(__('admin/home.side'))->badge()->formatStateUsing(fn (string $state): string => self::sideNames()[$state] ?? $state),
            TextColumn::make('text')->label(__('admin/common.text'))->searchable(),
        ], [
            SelectFilter::make('side')->label(__('admin/home.side'))->options(self::sideNames()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageComparisonPoints::route('/')];
    }
}
