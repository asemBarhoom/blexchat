<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqItemResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\FaqItem;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    public static function getNavigationLabel(): string
    {
        return __('admin/common.faq');
    }

    public static function getModelLabel(): string
    {
        return __('admin/survey.question_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/common.faq');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translations::input('question', __('admin/common.question')),
            Translations::input('answer', __('admin/survey.answer'), long: true),
            Translations::input('link_label', __('admin/home.link_text'), required: false),
            TextInput::make('link_url')->label(__('admin/common.link'))->requiredWith('link_label.en')
                ->helperText(__('admin/home.write_link_in_the_answer_where_the_link')),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('question')->label(__('admin/common.question'))->searchable()->wrap(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFaqItems::route('/')];
    }
}
