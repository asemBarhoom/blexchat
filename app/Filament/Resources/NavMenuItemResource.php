<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavMenuItemResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\NavMenuItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NavMenuItemResource extends Resource
{
    protected static ?string $model = NavMenuItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'قوائم النافبار';

    protected static ?string $modelLabel = 'عنصر قائمة';

    protected static ?string $pluralModelLabel = 'قوائم النافبار';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 15;

    /** @var array<string, string> */
    private const MENU_NAMES = ['product' => 'المنتج', 'solutions' => 'الحلول'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('menu')->label('القائمة')->options(self::MENU_NAMES)->required(),
            Select::make('channel')->label('شعار المنصة')->options(array_combine(NavMenuItem::CHANNELS, NavMenuItem::CHANNELS))
                ->helperText('لعناصر المنصات؛ يظهر شعارها بدل الأيقونة.')->requiredWithout('icon'),
            Select::make('icon')->label('الأيقونة')->options(array_combine(NavMenuItem::ICONS, NavMenuItem::ICONS))->requiredWithout('channel'),
            Translations::input('title', 'العنوان'),
            Translations::input('description', 'الوصف'),
            TextInput::make('target')->label('الرابط')->required()->helperText('صفحة مثل /signup أو قسم في الرئيسية مثل #try-it'),
            Translations::input('cta', 'نص زر بارز (اختياري)', required: false),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            TextColumn::make('menu')->label('القائمة')->badge()->formatStateUsing(fn (string $state): string => self::MENU_NAMES[$state] ?? $state),
            TextColumn::make('title')->label('العنوان')->searchable(),
            TextColumn::make('description')->label('الوصف'),
        ], [
            SelectFilter::make('menu')->label('القائمة')->options(self::MENU_NAMES),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNavMenuItems::route('/')];
    }
}
