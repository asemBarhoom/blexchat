<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\Creator;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreatorResource extends Resource
{
    protected static ?string $model = Creator::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'صُنّاع المحتوى';

    protected static ?string $modelLabel = 'صانع محتوى';

    protected static ?string $pluralModelLabel = 'صُنّاع المحتوى';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('المعرّف (بالإنجليزية بدون مسافات)')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('handle')->label('الحساب')->required(),
            Translations::input('name', 'الاسم'),
            Translations::input('followers', 'المتابعون'),
            Translations::input('quote', 'الاقتباس', long: true),
            HomeContentFields::image('photo', 'الصورة')->columnSpanFull(),
            HomeContentFields::active(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table, [
            ImageColumn::make('photo')->label('الصورة')->disk('public')->circular(),
            TextColumn::make('name')->label('الاسم')->searchable(),
            TextColumn::make('handle')->label('الحساب'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCreators::route('/')];
    }
}
