<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\Creator;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreatorResource extends Resource
{
    protected static ?string $model = Creator::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationLabel(): string
    {
        return __('admin/home.creators');
    }

    public static function getModelLabel(): string
    {
        return __('admin/home.creator');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/home.creators');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label(__('admin/home.key_english_no_spaces'))->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('handle')->label(__('admin/common.account'))->required(),
            Translations::input('name', __('admin/common.name')),
            Translations::input('followers', __('admin/channels.followers')),
            Translations::input('quote', __('admin/home.quote'), long: true),
            HomeContentFields::image('photo', __('admin/common.photo'))->columnSpanFull(),
            HomeContentFields::active(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            ImageColumn::make('photo')->label(__('admin/common.photo'))->disk('public')->circular(),
            TextColumn::make('name')->label(__('admin/common.name'))->searchable(),
            TextColumn::make('handle')->label(__('admin/common.account')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCreators::route('/')];
    }
}
