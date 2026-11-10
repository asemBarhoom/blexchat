<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirstMoveResource\Pages;
use App\Filament\Support\Translations;
use App\Models\FirstMove;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FirstMoveResource extends Resource
{
    /** @return array<string, string> */
    public static function triggerLabels(): array
    {
        return ['comment' => __('admin/first_moves.trigger_comment'), 'story' => __('admin/first_moves.trigger_story'), 'dm' => __('admin/common.dm')];
    }

    protected static ?string $model = FirstMove::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    public static function getNavigationLabel(): string
    {
        return __('admin/first_moves.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/first_moves.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/first_moves.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText(__('admin/first_moves.key_help')),
            Select::make('trigger')->label(__('admin/common.trigger'))->options(self::triggerLabels())->required()->native(false)
                ->helperText(__('admin/first_moves.trigger_help')),
            TextInput::make('template')->label(__('admin/first_moves.template'))->alphaDash()->maxLength(50)
                ->helperText(__('admin/first_moves.template_help')),
            Translations::input('title', __('admin/common.title')),
            Translations::input('line', __('admin/common.description'), long: true),

            Section::make(__('admin/first_moves.example'))->schema([
                TextInput::make('incoming_who')->label(__('admin/first_moves.incoming_who'))->required()->maxLength(50)->extraInputAttributes(['dir' => 'ltr']),
                Translations::input('incoming_kind', __('admin/first_moves.incoming_kind')),
                Translations::input('incoming_text', __('admin/first_moves.incoming_text')),
                Translations::input('reply', __('admin/first_moves.reply'), long: true),
            ]),

            Toggle::make('active')->label(__('admin/common.shown'))->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('admin/common.title'))->searchable(),
                TextColumn::make('key')->label(__('admin/common.key'))->badge()->color('gray'),
                TextColumn::make('trigger')->label(__('admin/common.trigger'))->formatStateUsing(fn (string $state): string => self::triggerLabels()[$state] ?? $state),
                TextColumn::make('template')->label(__('admin/first_moves.template_column'))->placeholder('—'),
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
        return ['index' => Pages\ManageFirstMoves::route('/')];
    }
}
