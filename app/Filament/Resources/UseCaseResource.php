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

    public static function getNavigationLabel(): string
    {
        return __('admin/home.use_cases');
    }

    public static function getModelLabel(): string
    {
        return __('admin/home.use_case');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/home.use_cases');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('slug')->label(__('admin/home.solution_page_link_solutions'))->required()->alphaDash()->unique(ignoreRecord: true),
            Translations::input('name', __('admin/common.name')),
            Translations::input('line', __('admin/common.description')),
            Select::make('shape')->label(__('admin/common.card_shape'))->options(array_combine(UseCase::SHAPES, UseCase::SHAPES))->required(),
            Select::make('tone')->label(__('admin/common.card_tone'))->options(array_combine(UseCase::TONES, UseCase::TONES))->required(),
            HomeContentFields::active(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('name')->label(__('admin/common.name'))->searchable(),
            TextColumn::make('line')->label(__('admin/common.description')),
            TextColumn::make('slug')->label(__('admin/common.link')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUseCases::route('/')];
    }
}
