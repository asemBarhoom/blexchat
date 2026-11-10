<?php

namespace App\Filament\Resources;

use App\Filament\Pages\ManagePricingPage;
use App\Filament\Resources\PricingFaqItemResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\PricingFaqItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricingFaqItemResource extends Resource
{
    protected static ?string $model = PricingFaqItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    public static function getNavigationLabel(): string
    {
        return __('admin/pricing.pricing_page_questions');
    }

    public static function getModelLabel(): string
    {
        return __('admin/survey.question_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/pricing.pricing_page_questions');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return ManagePricingPage::pricingGroup();
    }

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translations::input('question', __('admin/common.question')),
            Translations::input('answer', __('admin/survey.answer'), long: true),
            Text::make(ManagePricingPage::figuresHelp()),
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
        return ['index' => Pages\ManagePricingFaqItems::route('/')];
    }
}
