<?php

namespace App\Filament\Resources;

use App\Filament\Pages\ManagePricingPage;
use App\Filament\Resources\PricingCompareGroupResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\PricingCompareGroup;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PricingCompareGroupResource extends Resource
{
    protected static ?string $model = PricingCompareGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'مجموعات المقارنة';

    protected static ?string $modelLabel = 'مجموعة';

    protected static ?string $pluralModelLabel = 'مجموعات جدول المقارنة';

    protected static string|\UnitEnum|null $navigationGroup = ManagePricingPage::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('المعرّف')->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText('ثابت يستخدمه الفرونت، مثل automation.'),
            Translations::input('title', 'العنوان'),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('rows')), [
            TextColumn::make('title')->label('العنوان')->searchable(),
            TextColumn::make('key')->label('المعرّف')->badge()->color('gray'),
            TextColumn::make('rows_count')->label('الصفوف')->numeric(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePricingCompareGroups::route('/')];
    }
}
