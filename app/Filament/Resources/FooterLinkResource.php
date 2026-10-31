<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FooterLinkResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\FooterLink;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FooterLinkResource extends Resource
{
    protected static ?string $model = FooterLink::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'روابط التذييل';

    protected static ?string $modelLabel = 'رابط';

    protected static ?string $pluralModelLabel = 'روابط التذييل';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 16;

    /** @var array<string, string> */
    private const GROUP_NAMES = ['product' => 'عمود المنتج', 'solutions' => 'عمود الحلول', 'account' => 'عمود الحساب', 'legal' => 'الروابط القانونية'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('group')->label('المكان')->options(self::GROUP_NAMES)->required(),
            Translations::input('label', 'النص'),
            TextInput::make('url')->label('الرابط')->required(),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('group')->label('المكان')->badge()->formatStateUsing(fn (string $state): string => self::GROUP_NAMES[$state] ?? $state),
            TextColumn::make('label')->label('النص')->searchable(),
            TextColumn::make('url')->label('الرابط'),
        ], [
            SelectFilter::make('group')->label('المكان')->options(self::GROUP_NAMES),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFooterLinks::route('/')];
    }
}
