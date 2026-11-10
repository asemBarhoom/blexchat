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

    public static function getNavigationLabel(): string
    {
        return __('admin/pricing.comparison_groups');
    }

    public static function getModelLabel(): string
    {
        return __('admin/pricing.group');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/pricing.comparison_table_groups');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return ManagePricingPage::pricingGroup();
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText(__('admin/pricing.a_fixed_value_the_frontend_uses_like_aut')),
            Translations::input('title', __('admin/common.title')),
            HomeContentFields::active(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return HomeContentFields::table($table->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('rows')), [
            TextColumn::make('title')->label(__('admin/common.title'))->searchable(),
            TextColumn::make('key')->label(__('admin/common.key'))->badge()->color('gray'),
            TextColumn::make('rows_count')->label(__('admin/pricing.rows'))->numeric(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePricingCompareGroups::route('/')];
    }
}
