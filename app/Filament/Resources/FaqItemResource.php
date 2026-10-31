<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqItemResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\FaqItem;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'الأسئلة الشائعة';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'الأسئلة الشائعة';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translations::input('question', 'السؤال'),
            Translations::input('answer', 'الإجابة', long: true),
            Translations::input('link_label', 'نص الرابط', required: false),
            TextInput::make('link_url')->label('الرابط')->requiredWith('link_label.en'),
            Toggle::make('claim_unverified')->label('ادعاء يحتاج توثيقاً قبل الإطلاق'),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('question')->label('السؤال')->searchable()->wrap(),
            IconColumn::make('claim_unverified')->label('يحتاج توثيقاً')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFaqItems::route('/')];
    }
}
