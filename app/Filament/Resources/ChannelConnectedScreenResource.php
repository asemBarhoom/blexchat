<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelConnectedScreenResource\Pages;
use App\Filament\Support\Translations;
use App\Models\ChannelConnectedScreen;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ChannelConnectedScreenResource extends Resource
{
    /** @return array<string, string> */
    public static function platformLabels(): array
    {
        return ['instagram' => __('admin/connected_screens.instagram'), 'whatsapp' => __('admin/connected_screens.whatsapp'), 'tiktok' => __('admin/connected_screens.tiktok'), 'messenger' => __('admin/connected_screens.messenger')];
    }

    protected static ?string $model = ChannelConnectedScreen::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    public static function getNavigationLabel(): string
    {
        return __('admin/connected_screens.nav');
    }

    public static function getModelLabel(): string
    {
        return __('admin/connected_screens.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/connected_screens.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->label(__('admin/common.platform'))->options(self::platformLabels())->required()
                ->unique(ignoreRecord: true)
                ->helperText(__('admin/connected_screens.platform_help')),
            Translations::input('channel_name', __('admin/connected_screens.channel_name')),
            Translations::input('title', __('admin/common.title')),
            Translations::input('lead', __('admin/connected_screens.lead'), long: true),
            Translations::input('account_kind', __('admin/common.account_kind')),

            Section::make(__('admin/connected_screens.buttons'))->schema([
                Translations::input('status_label', __('admin/connected_screens.status_label')),
                Translations::input('continue_label', __('admin/connected_screens.continue_label')),
                Translations::input('connect_another_label', __('admin/connected_screens.connect_another')),
            ]),

            Toggle::make('active')->label(__('admin/common.shown'))->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')->label(__('admin/common.platform'))->badge()
                    ->formatStateUsing(fn (string $state): string => self::platformLabels()[$state] ?? $state),
                TextColumn::make('title')->label(__('admin/common.title'))->searchable(),
                TextColumn::make('account_kind')->label(__('admin/common.account_kind')),
                ToggleColumn::make('active')->label(__('admin/common.shown')),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChannelConnectedScreens::route('/')];
    }
}
