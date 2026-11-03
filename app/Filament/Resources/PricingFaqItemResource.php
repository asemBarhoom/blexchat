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

    protected static ?string $navigationLabel = 'أسئلة صفحة الأسعار';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'أسئلة صفحة الأسعار';

    protected static string|\UnitEnum|null $navigationGroup = ManagePricingPage::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translations::input('question', 'السؤال'),
            Translations::input('answer', 'الإجابة', long: true),
            Text::make(ManagePricingPage::FIGURES_HELP),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('question')->label('السؤال')->searchable()->wrap(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePricingFaqItems::route('/')];
    }
}
