<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelOptionResource\Pages;
use App\Filament\Support\Translations;
use App\Models\ChannelOption;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelOptionResource extends Resource
{
    protected static ?string $model = ChannelOption::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'قنوات البداية';

    protected static ?string $modelLabel = 'قناة';

    protected static ?string $pluralModelLabel = 'قنوات البداية';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->label('المنصة')->required()->unique(ignoreRecord: true)
                ->options(array_combine(ChannelOption::PLATFORMS, ChannelOption::PLATFORMS))
                ->helperText('ثابت يستخدمه الفرونت للأيقونة ورابط الربط.'),
            Translations::input('name', 'الاسم'),
            Translations::input('line', 'الوصف المختصر'),
            Toggle::make('active')->label('معروضة')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('channels'))
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('platform')->label('المنصة')->badge()->color('gray'),
                TextColumn::make('line')->label('الوصف المختصر'),
                TextColumn::make('channels_count')->label('الحسابات المربوطة')->numeric(),
                ToggleColumn::make('active')->label('معروضة'),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChannelOptions::route('/')];
    }
}
