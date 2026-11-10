<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelOptionResource\Pages;
use App\Filament\Support\Translations;
use App\Models\ChannelOption;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelOptionResource extends Resource
{
    protected static ?string $model = ChannelOption::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getNavigationLabel(): string
    {
        return __('admin/channel_options.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/channel_options.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/channel_options.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->label(__('admin/common.platform'))->required()->unique(ignoreRecord: true)
                ->options(array_combine(ChannelOption::PLATFORMS, ChannelOption::PLATFORMS))
                ->helperText(__('admin/channel_options.platform_help')),
            Translations::input('name', __('admin/common.name')),
            Translations::input('line', __('admin/channel_options.line')),
            Toggle::make('active')->label(__('admin/common.shown'))->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('channels'))
            ->columns([
                TextColumn::make('name')->label(__('admin/common.name'))->searchable(),
                TextColumn::make('platform')->label(__('admin/common.platform'))->badge()->color('gray'),
                TextColumn::make('line')->label(__('admin/channel_options.line')),
                TextColumn::make('channels_count')->label(__('admin/channel_options.channels_count'))->numeric(),
                ToggleColumn::make('active')->label(__('admin/common.shown')),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChannelOptions::route('/')];
    }
}
