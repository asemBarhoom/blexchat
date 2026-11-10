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

    public static function getNavigationLabel(): string
    {
        return __('admin/home.numbers');
    }

    public static function getModelLabel(): string
    {
        return __('admin/home.number_3');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/home.numbers');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translations::input('value', __('admin/home.value')),
            Translations::input('label', __('admin/common.description')),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('value')->label(__('admin/home.value')),
            TextColumn::make('label')->label(__('admin/common.description')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHomeStats::route('/')];
    }
}
