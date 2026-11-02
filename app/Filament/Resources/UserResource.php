<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المحتوى';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الحساب')->schema([
                TextInput::make('name')->label('الاسم')->maxLength(255),
                TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('business_name')->label('اسم النشاط التجاري')->maxLength(255),
                DateTimePicker::make('email_verified_at')->label('تاريخ تأكيد البريد')
                    ->helperText('يُملأ تلقائياً عند أول دخول برابط البريد أو Google.'),
            ])->columns(2),

            Section::make('لوحة التحكم')->schema([
                Toggle::make('is_admin')->label('مشرف (يدخل لوحة التحكم)')->live(),
                // Owners sign in without a password; only an admin needs one, for this panel.
                TextInput::make('password')->label('كلمة المرور')->password()->revealable()->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('is_admin'))
                    ->helperText(fn (string $operation): string => $operation === 'edit' ? 'اتركها فارغة للإبقاء على الحالية.' : 'مطلوبة للمشرفين فقط.')
                    ->visible(fn (Get $get): bool => (bool) $get('is_admin')),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['channels', 'automations']))
            ->columns([
                TextColumn::make('name')->label('الاسم')->default('—')->searchable()->sortable(),
                TextColumn::make('email')->label('البريد الإلكتروني')->searchable()->copyable(),
                TextColumn::make('business_name')->label('النشاط التجاري')->default('—')->searchable()->toggleable(),
                TextColumn::make('channels_count')->label('القنوات')->numeric()->sortable(),
                TextColumn::make('automations_count')->label('الأتمتة')->numeric()->sortable(),
                IconColumn::make('google_id')->label('Google')->boolean()
                    ->state(fn (User $record): bool => filled($record->google_id))->toggleable(),
                IconColumn::make('is_admin')->label('مشرف')->boolean(),
                TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_admin')->label('مشرف'),
                TernaryFilter::make('email_verified_at')->label('البريد مؤكَّد')->nullable(),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف')->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الحساب')->schema([
                TextEntry::make('name')->label('الاسم')->default('—'),
                TextEntry::make('email')->label('البريد الإلكتروني')->copyable(),
                TextEntry::make('business_name')->label('النشاط التجاري')->default('—'),
                TextEntry::make('email_verified_at')->label('تأكيد البريد')->dateTime('Y-m-d H:i')->placeholder('غير مؤكَّد'),
                IconEntry::make('google_id')->label('مرتبط بـ Google')->boolean()
                    ->state(fn (User $record): bool => filled($record->google_id)),
                IconEntry::make('is_admin')->label('مشرف')->boolean(),
                TextEntry::make('created_at')->label('تاريخ التسجيل')->dateTime('Y-m-d H:i'),
            ])->columns(2),
            Section::make('النشاط')->schema([
                TextEntry::make('channels_count')->label('القنوات')->state(fn (User $record): int => $record->channels()->count()),
                TextEntry::make('automations_count')->label('الأتمتة')->state(fn (User $record): int => $record->automations()->count()),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
