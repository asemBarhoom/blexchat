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

    protected static ?string $navigationLabel = 'ردود التجربة الحية';

    protected static ?string $modelLabel = 'قاعدة رد';

    protected static ?string $pluralModelLabel = 'ردود التجربة الحية';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('المعرّف')->required()->alphaDash()->unique(ignoreRecord: true),
            TagsInput::make('keywords')->label('الكلمات المفتاحية (أي لغة)')->helperText('أول قاعدة تطابق كلمة من كلماتها في تعليق الزائر هي التي ترد.')->required(),
            Translations::input('reply', 'الرد'),
            Translations::input('action', 'زر الرد'),
            Translations::input('result', 'النتيجة'),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('keywords')->label('الكلمات')->badge(),
            TextColumn::make('reply')->label('الرد')->wrap(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDemoReplyRules::route('/')];
    }
}
