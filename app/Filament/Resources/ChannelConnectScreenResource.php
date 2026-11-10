<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelConnectScreenResource\Pages;
use App\Filament\Support\Translations;
use App\Models\ChannelConnectScreen;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ChannelConnectScreenResource extends Resource
{
    /** @return array<string, string> */
    public static function methodLabels(): array
    {
        return ['meta' => __('admin/channel_screens.via_meta'), 'tiktok' => __('admin/channel_screens.via_tiktok')];
    }

    protected static ?string $model = ChannelConnectScreen::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    public static function getNavigationLabel(): string
    {
        return __('admin/channel_screens.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/channel_screens.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/channel_screens.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        $locales = config('app.supported_locales');

        return $schema->components([
            Section::make(__('admin/common.channel'))->schema([
                Select::make('platform')->label(__('admin/common.platform'))->required()->unique(ignoreRecord: true)
                    ->options(array_combine(ChannelConnectScreen::PLATFORMS, ['Instagram', 'WhatsApp', 'TikTok', 'Messenger'])),
                Select::make('method')->label(__('admin/channel_screens.method'))->options(self::methodLabels())->required()->default('meta')
                    ->helperText(__('admin/channel_screens.method_help')),
                Translations::input('name', __('admin/common.name')),
                Translations::input('cta', __('admin/channel_screens.cta')),
                Translations::input('lead', __('admin/channel_screens.lead'), long: true),
                Translations::input('account_kind', __('admin/channel_screens.account_kind'), required: false),
                Translations::input('can', __('admin/channel_screens.can'), required: false),
                Toggle::make('active')->label(__('admin/common.visible'))->default(true),
            ])->columns(2),

            Section::make(__('admin/channel_screens.steps'))->schema([
                Grid::make(count($locales))->schema(array_map(
                    fn (string $locale): TagsInput => TagsInput::make("steps.{$locale}")
                        ->label(__('admin/channel_screens.steps_in', ['locale' => Translations::localeName($locale)]))
                        ->placeholder(__('admin/channel_screens.steps_placeholder'))
                        ->reorderable()
                        ->required(Translations::isFallback($locale)),
                    $locales,
                )),
            ]),

            Section::make(__('admin/channel_screens.more_options'))->schema([
                Grid::make(count($locales))->schema(array_map(
                    fn (string $locale): Repeater => Repeater::make("options.{$locale}")
                        ->label(__('admin/channel_screens.options_in', ['locale' => Translations::localeName($locale)]))
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel(__('admin/common.add_option'))
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            Translations::field('title', __('admin/common.title'), $locale, required: true),
                            Translations::field('note', __('admin/channel_screens.note'), $locale, required: false),
                            TextInput::make('url')->label(__('admin/channel_screens.help_url'))->url(),
                        ]),
                    $locales,
                )),
            ])->collapsible(),

            Section::make(__('admin/channel_screens.while_connecting'))->schema([
                Translations::input('reconnect_cta', __('admin/channel_screens.reconnect_cta')),
                Translations::input('waiting_in', __('admin/channel_screens.waiting_in')),
            ]),

            Section::make(__('admin/channel_screens.ineligible'))->schema([
                Translations::input('ineligible_title', __('admin/common.title')),
                Translations::input('ineligible_text', __('admin/common.text'), long: true),
                Translations::input('ineligible_kind', __('admin/channel_screens.ineligible_kind'), required: false),
                Translations::input('ineligible_how', __('admin/channel_screens.ineligible_how'), required: false),
                TextInput::make('ineligible_help_url')->label(__('admin/channel_screens.ineligible_help_url'))->url(),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin/common.channel'))->searchable(),
                TextColumn::make('platform')->label(__('admin/common.platform'))->badge()->color('gray'),
                TextColumn::make('method')->label(__('admin/channel_screens.method'))->formatStateUsing(fn (string $state): string => self::methodLabels()[$state] ?? $state),
                TextColumn::make('cta')->label(__('admin/channel_screens.cta_column')),
                ToggleColumn::make('active')->label(__('admin/common.visible')),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete'))->modalDescription(__('admin/channel_screens.delete_note')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannelConnectScreens::route('/'),
            'create' => Pages\CreateChannelConnectScreen::route('/create'),
            'edit' => Pages\EditChannelConnectScreen::route('/{record}/edit'),
        ];
    }
}
