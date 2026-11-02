<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Support\Translations;
use App\Models\Plan;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    public const NAVIGATION_GROUP = 'التسجيل والاشتراكات';

    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'الخطط';

    protected static ?string $modelLabel = 'خطة';

    protected static ?string $pluralModelLabel = 'الخطط';

    protected static string|\UnitEnum|null $navigationGroup = self::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')->label('المعرّف')->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText('ثابت يستخدمه الفرونت، مثل essential. خطة free مطلوبة دائماً: إليها يعود الحساب بعد انتهاء التجربة.')
                ->disabled(fn (?Plan $record): bool => $record?->slug === Plan::FREE_SLUG),
            Translations::input('name', 'الاسم'),
            Translations::input('tagline', 'الوصف القصير', required: false),

            Section::make('السعر والحدود')->schema([
                TextInput::make('monthly_price')->label('السعر الشهري ($)')->numeric()->integer()->minValue(0)->required()
                    ->helperText('0 = خطة مجانية.'),
                TextInput::make('annual_price')->label('السعر الشهري بالدفع السنوي ($)')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('contacts_limit')->label('حد جهات الاتصال')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('trial_days')->label('أيام التجربة المجانية')->numeric()->integer()->minValue(0)->maxValue(365)->default(0)
                    ->helperText('0 = بدون تجربة: تُدفع الخطة من اليوم.'),
            ])->columns(2),

            Grid::make(count(config('app.supported_locales')))->schema(array_map(
                fn (string $locale): TagsInput => TagsInput::make("features.{$locale}")
                    ->label('المزايا — '.Translations::localeName($locale))
                    ->placeholder('اكتب ميزة واضغط Enter')
                    ->reorderable(),
                config('app.supported_locales'),
            ))->columnSpanFull(),

            Grid::make(3)->schema([
                Toggle::make('has_ai')->label('يشمل Blexchat AI'),
                Toggle::make('is_recommended')->label('"اختيارنا لك"'),
                Toggle::make('active')->label('معروضة للبيع')->default(true),
            ])->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('subscriptions'))
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('slug')->label('المعرّف')->badge()->color('gray'),
                TextColumn::make('monthly_price')->label('شهري')->money('USD'),
                TextColumn::make('annual_price')->label('سنوي / شهر')->money('USD'),
                TextColumn::make('contacts_limit')->label('جهات الاتصال')->numeric(),
                TextColumn::make('trial_days')->label('أيام التجربة')->numeric(),
                IconColumn::make('is_recommended')->label('اختيارنا')->boolean(),
                TextColumn::make('subscriptions_count')->label('المشتركون')->numeric(),
                ToggleColumn::make('active')->label('معروضة'),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                // Free is where expired trials land, and a plan in use keeps its subscribers' history.
                DeleteAction::make()->label('حذف')
                    ->hidden(fn (Plan $record): bool => $record->slug === Plan::FREE_SLUG || $record->subscriptions_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlans::route('/')];
    }
}
