<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DemoReplyRuleResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\DemoReplyRule;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DemoReplyRuleResource extends Resource
{
    protected static ?string $model = DemoReplyRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    public static function getNavigationLabel(): string
    {
        return __('admin/home.live_demo_replies');
    }

    public static function getModelLabel(): string
    {
        return __('admin/home.reply_rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/home.live_demo_replies');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->unique(ignoreRecord: true),
            TagsInput::make('keywords')->label(__('admin/home.keywords_any_language'))->helperText(__('admin/home.the_first_rule_with_a_word_in_the_visito'))->required(),
            Translations::input('reply', __('admin/common.reply')),
            Translations::input('action', __('admin/common.reply_button')),
            Translations::input('result', __('admin/common.result')),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('keywords')->label(__('admin/home.words'))->badge(),
            TextColumn::make('reply')->label(__('admin/common.reply'))->wrap(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDemoReplyRules::route('/')];
    }
}
