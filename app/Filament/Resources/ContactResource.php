<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactResource extends Resource
{
    /** @var array<string, string> */
    public const GENDER_LABELS = ['female' => 'أنثى', 'male' => 'ذكر'];

    protected static ?string $model = Contact::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'جهات الاتصال';

    protected static ?string $modelLabel = 'جهة اتصال';

    protected static ?string $pluralModelLabel = 'جهات الاتصال';

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('صاحب الحساب')->relationship('user', 'email')->searchable()->preload()->required()
                ->live()->afterStateUpdated(fn (Set $set): mixed => $set('channel_id', null)),
            Select::make('channel_id')->label('القناة المتصلة')
                ->relationship('channel', 'username', fn (Builder $query, Get $get): Builder => $query->where('user_id', $get('user_id')))
                ->getOptionLabelFromRecordUsing(fn (Channel $record): string => (Contact::PLATFORMS[$record->platform] ?? $record->platform).' — '.($record->username ?? $record->account_id))
                ->helperText('اتركها فارغة لجهة أُضيفت يدوياً.'),
            Select::make('platform')->label('المنصة')->options(Contact::PLATFORMS)->required()->default('instagram'),
            TextInput::make('platform_id')->label('معرّف المنصة')->maxLength(255)
                ->helperText('معرّف الشخص لدى المنصة، يربطه بمحادثاته وتعليقاته.'),
            TextInput::make('name')->label('الاسم')->required()->maxLength(200),
            TextInput::make('handle')->label('اسم المستخدم أو الرقم')->maxLength(255),
            TextInput::make('phone')->label('الجوال')->tel()->maxLength(30),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->maxLength(255),
            TextInput::make('city')->label('المدينة')->maxLength(100),
            Select::make('gender')->label('الجنس')->options(self::GENDER_LABELS),
            TextInput::make('language')->label('اللغة')->maxLength(10)->placeholder('ar'),
            TagsInput::make('tags')->label('الوسوم')->columnSpanFull(),
            Select::make('source_automation_id')->label('الأتمتة التي جلبته')
                ->relationship('sourceAutomation', 'name', fn (Builder $query, Get $get): Builder => $query->where('user_id', $get('user_id')))
                ->getOptionLabelFromRecordUsing(fn (Automation $record): string => $record->name),
            DateTimePicker::make('first_seen_at')->label('أول ظهور')->required()->default(now()),
            DateTimePicker::make('last_active_at')->label('آخر نشاط'),
            DateTimePicker::make('consented_at')->label('تاريخ الموافقة على المراسلة'),
            Toggle::make('added_manually')->label('أُضيف يدوياً'),
            Toggle::make('subscribed')->label('مشترك في الرسائل')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'sourceAutomation']))
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(['name', 'handle', 'phone', 'email'])
                    ->description(fn (Contact $record): ?string => $record->handle),
                TextColumn::make('platform')->label('القناة')->badge()
                    ->formatStateUsing(fn (string $state): string => Contact::PLATFORMS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp' => 'success',
                        'messenger' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('user.email')->label('صاحب الحساب')->searchable()->toggleable(),
                TextColumn::make('tags')->label('الوسوم')->badge()->placeholder('بلا وسوم'),
                TextColumn::make('sourceAutomation.name')->label('المصدر')
                    ->placeholder(fn (Contact $record): string => $record->added_manually ? 'أُضيف يدوياً' : 'راسل مباشرة'),
                TextColumn::make('city')->label('المدينة')->placeholder('—')->toggleable(),
                IconColumn::make('subscribed')->label('مشترك')->boolean(),
                TextColumn::make('last_active_at')->label('آخر نشاط')->since()->placeholder('—')->sortable(),
                TextColumn::make('first_seen_at')->label('أول ظهور')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->filters([
                SelectFilter::make('platform')->label('القناة')->options(Contact::PLATFORMS),
                SelectFilter::make('user_id')->label('صاحب الحساب')->relationship('user', 'email')->searchable()->preload(),
                TernaryFilter::make('added_manually')->label('أُضيف يدوياً'),
                TernaryFilter::make('subscribed')->label('مشترك'),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف')->modalDescription('تبقى محادثاته في صندوق الوارد.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContacts::route('/')];
    }
}
