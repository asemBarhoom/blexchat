<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutomationTemplateResource\Pages;
use App\Filament\Support\Translations;
use App\Models\AutomationTemplate;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AutomationTemplateResource extends Resource
{
    public static function builds(): array
    {
        return ['quick' => __('admin/templates.build_quick'), 'flow' => __('admin/templates.build_flow')];
    }

    public static function goals(): array
    {
        return ['sell' => __('admin/templates.goal_sell'), 'grow' => __('admin/templates.goal_grow'), 'support' => __('admin/templates.goal_support'), 'book' => __('admin/templates.goal_book')];
    }

    public static function triggers(): array
    {
        return ['comment' => __('admin/templates.trigger_comment'), 'story' => __('admin/templates.trigger_story'), 'dm' => __('admin/templates.trigger_dm'), 'ad' => __('admin/templates.trigger_ad')];
    }

    public static function matches(): array
    {
        return ['words' => __('admin/templates.match_words'), 'any' => __('admin/templates.match_any')];
    }

    protected static ?string $model = AutomationTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    public static function getNavigationLabel(): string
    {
        return __('admin/templates.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/templates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/templates.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_content');
    }

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $fillsWizard = fn (Get $get): bool => $get('build') === 'quick' && $get('trigger') !== 'ad';

        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('key')->label(__('admin/templates.key'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText(__('admin/templates.key_help')),
                Toggle::make('is_starter')->label(__('admin/templates.starter_for_trigger'))->live()
                    ->helperText(__('admin/templates.starter_help'))
                    ->rules([fn (Get $get, ?AutomationTemplate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if ($value && AutomationTemplate::starters()->where('trigger', $get('trigger'))->whereKeyNot($record?->id)->exists()) {
                            $fail(__('admin/templates.starter_taken'));
                        }
                    }]),
            ])->columnSpanFull(),
            Translations::input('name', __('admin/common.name')),
            Translations::input('line', __('admin/templates.description'), required: false, long: true),

            Grid::make(4)->schema([
                Select::make('trigger')->label(__('admin/templates.trigger'))->options(self::triggers())->required()->live(),
                Select::make('build')->label(__('admin/common.type'))->options(self::builds())->required()->default('quick')->live(),
                Select::make('goal')->label(__('admin/templates.goal'))->options(self::goals())
                    ->required(fn (Get $get): bool => ! $get('is_starter')),
                Toggle::make('locked')->label(__('admin/templates.locked'))->inline(false),
            ])->columnSpanFull(),

            Section::make(__('admin/templates.wizard_fill'))->visible($fillsWizard)->schema([
                Select::make('match')->label(__('admin/templates.match'))->options(self::matches())->required($fillsWizard)->live(),
                Grid::make(count(config('app.supported_locales')))->visible(fn (Get $get): bool => $get('match') === 'words')->schema(array_map(
                    fn (string $locale): TagsInput => TagsInput::make("keywords.{$locale}")
                        ->label(__('admin/templates.keywords_in', ['locale' => Translations::localeName($locale)]))
                        ->placeholder(__('admin/templates.keywords_placeholder')),
                    config('app.supported_locales'),
                ))->columnSpanFull(),
                Translations::input('public_reply', __('admin/templates.public_reply'), required: false)
                    ->visible(fn (Get $get): bool => $get('trigger') === 'comment'),
                Translations::input('dm_text', __('admin/templates.dm_text'), required: true, long: true),
                Translations::input('dm_button', __('admin/common.button_text'), required: false),
                TextInput::make('link_url')->label(__('admin/templates.link_url'))->url()->maxLength(2048)->columnSpanFull(),
            ])->columnSpanFull(),

            Toggle::make('active')->label(__('admin/templates.shown'))->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin/common.name'))->searchable()->wrap(),
                TextColumn::make('key')->label(__('admin/templates.key'))->badge()->color('gray'),
                TextColumn::make('trigger')->label(__('admin/templates.trigger'))->formatStateUsing(fn (string $state): string => self::triggers()[$state] ?? $state),
                TextColumn::make('build')->label(__('admin/common.type'))->badge()->formatStateUsing(fn (string $state): string => self::builds()[$state] ?? $state),
                TextColumn::make('goal')->label(__('admin/templates.goal'))->formatStateUsing(fn (?string $state): string => self::goals()[$state] ?? '—'),
                IconColumn::make('is_starter')->label(__('admin/templates.starter'))->boolean(),
                IconColumn::make('locked')->label(__('admin/templates.paid'))->boolean(),
                ToggleColumn::make('active')->label(__('admin/templates.shown')),
            ])
            ->filters([
                SelectFilter::make('trigger')->label(__('admin/templates.trigger'))->options(self::triggers()),
                TernaryFilter::make('is_starter')->label(__('admin/templates.starter')),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                // The wizard opens with a starter whenever no template is named.
                DeleteAction::make()->label(__('admin/common.delete'))->hidden(fn (AutomationTemplate $record): bool => $record->is_starter),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAutomationTemplates::route('/')];
    }
}
