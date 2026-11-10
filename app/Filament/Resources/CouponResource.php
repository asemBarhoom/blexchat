<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use App\Models\Plan;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {
        return __('admin/coupons.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/coupons.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/coupons.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('admin/coupons.code'))->required()->alphaDash()->maxLength(50)
                // Unique ignoring case, as codes are matched.
                ->rule(fn (?Coupon $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    $taken = Coupon::where('code', Coupon::normalize((string) $value))
                        ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($taken) {
                        $fail(__('admin/coupons.code_taken'));
                    }
                })
                ->dehydrateStateUsing(fn (string $state): string => Coupon::normalize($state))
                ->helperText(__('admin/coupons.code_help')),
            TextInput::make('percent_off')->label(__('admin/coupons.percent_off'))->numeric()->integer()->minValue(1)->maxValue(100)->required(),
            Select::make('plans')->label(__('admin/coupons.plans'))->multiple()->preload()
                ->relationship('plans', 'slug', fn (Builder $query): Builder => $query->where('monthly_price', '>', 0)->orderBy('sort'))
                ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name)
                ->helperText(__('admin/coupons.plans_help')),
            DateTimePicker::make('expires_at')->label(__('admin/coupons.expires_at'))->helperText(__('admin/coupons.expires_help')),
            TextInput::make('usage_limit')->label(__('admin/coupons.usage_limit'))->numeric()->integer()->minValue(1)
                ->helperText(__('admin/coupons.usage_limit_help')),
            Toggle::make('active')->label(__('admin/common.enabled'))->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('admin/coupons.code'))->searchable()->badge()->color('gray'),
                TextColumn::make('percent_off')->label(__('admin/coupons.discount'))->suffix('%'),
                TextColumn::make('plans.slug')->label(__('admin/common.plans'))->badge()->placeholder(__('admin/coupons.all_paid_plans')),
                TextColumn::make('expires_at')->label(__('admin/coupons.expires_at'))->dateTime()->placeholder(__('admin/coupons.never')),
                TextColumn::make('times_used')->label(__('admin/coupons.times_used'))->numeric(),
                TextColumn::make('usage_limit')->label(__('admin/coupons.usage_limit'))->numeric()->placeholder(__('admin/coupons.no_limit')),
                ToggleColumn::make('active')->label(__('admin/common.enabled')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCoupons::route('/')];
    }
}
