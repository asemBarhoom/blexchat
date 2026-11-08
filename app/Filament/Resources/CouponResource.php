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

    protected static ?string $navigationLabel = 'الكوبونات';

    protected static ?string $modelLabel = 'كوبون';

    protected static ?string $pluralModelLabel = 'الكوبونات';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('الرمز')->required()->alphaDash()->maxLength(50)
                // Unique ignoring case, as codes are matched.
                ->rule(fn (?Coupon $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    $taken = Coupon::where('code', Coupon::normalize((string) $value))
                        ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($taken) {
                        $fail('هذا الرمز مستخدم بالفعل.');
                    }
                })
                ->dehydrateStateUsing(fn (string $state): string => Coupon::normalize($state))
                ->helperText('يُحفظ بأحرف كبيرة، ويُقبل من العميل بأي حالة أحرف، مثل BLEX20.'),
            TextInput::make('percent_off')->label('نسبة الخصم (%)')->numeric()->integer()->minValue(1)->maxValue(100)->required(),
            Select::make('plans')->label('الخطط المشمولة')->multiple()->preload()
                ->relationship('plans', 'slug', fn (Builder $query): Builder => $query->where('monthly_price', '>', 0)->orderBy('sort'))
                ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name)
                ->helperText('اتركها فارغة ليشمل الكوبون كل الخطط المدفوعة. لا يُطبَّق الكوبون على الخطة المجانية ولا على خطة بتجربة مجانية.'),
            DateTimePicker::make('expires_at')->label('ينتهي في')->helperText('اتركه فارغاً ليبقى الكوبون بلا تاريخ انتهاء.'),
            TextInput::make('usage_limit')->label('حد الاستخدام')->numeric()->integer()->minValue(1)
                ->helperText('اتركه فارغاً لاستخدام بلا حد. يُحتسب الاستخدام مرة عند نجاح كل دفعة بهذا الكوبون.'),
            Toggle::make('active')->label('فعّال')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الرمز')->searchable()->badge()->color('gray'),
                TextColumn::make('percent_off')->label('الخصم')->suffix('%'),
                TextColumn::make('plans.slug')->label('الخطط')->badge()->placeholder('كل الخطط المدفوعة'),
                TextColumn::make('expires_at')->label('ينتهي في')->dateTime()->placeholder('بلا انتهاء'),
                TextColumn::make('times_used')->label('مرات الاستخدام')->numeric(),
                TextColumn::make('usage_limit')->label('حد الاستخدام')->numeric()->placeholder('بلا حد'),
                ToggleColumn::make('active')->label('فعّال'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCoupons::route('/')];
    }
}
