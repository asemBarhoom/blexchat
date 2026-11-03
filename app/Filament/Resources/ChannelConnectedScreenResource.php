<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelConnectedScreenResource\Pages;
use App\Filament\Support\Translations;
use App\Models\ChannelConnectedScreen;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ChannelConnectedScreenResource extends Resource
{
    /** @var array<string, string> */
    public const PLATFORM_LABELS = ['instagram' => 'إنستغرام', 'whatsapp' => 'واتساب', 'tiktok' => 'تيك توك', 'messenger' => 'ماسنجر'];

    protected static ?string $model = ChannelConnectedScreen::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'شاشة نجاح ربط القناة';

    protected static ?string $modelLabel = 'شاشة نجاح ربط';

    protected static ?string $pluralModelLabel = 'شاشات نجاح ربط القنوات';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->label('المنصة')->options(self::PLATFORM_LABELS)->required()
                ->unique(ignoreRecord: true)
                ->helperText('تظهر هذه النصوص بعد ربط قناة من هذه المنصة.'),
            Translations::input('channel_name', 'اسم القناة'),
            Translations::input('title', 'العنوان'),
            Translations::input('lead', 'النص التمهيدي', long: true),
            Translations::input('account_kind', 'نوع الحساب'),

            Section::make('الأزرار والحالة')->schema([
                Translations::input('status_label', 'شارة الحالة'),
                Translations::input('continue_label', 'زر المتابعة'),
                Translations::input('connect_another_label', 'رابط ربط قناة أخرى'),
            ]),

            Toggle::make('active')->label('معروضة')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')->label('المنصة')->badge()
                    ->formatStateUsing(fn (string $state): string => self::PLATFORM_LABELS[$state] ?? $state),
                TextColumn::make('title')->label('العنوان')->searchable(),
                TextColumn::make('account_kind')->label('نوع الحساب'),
                ToggleColumn::make('active')->label('معروضة'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChannelConnectedScreens::route('/')];
    }
}
