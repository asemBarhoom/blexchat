<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelAccessRequestResource\Pages;
use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelAccessRequestResource extends Resource
{
    /** @var array<string, string> */
    public const STATUS_LABELS = ['pending' => 'بانتظار الرد', 'approved' => 'مقبول', 'declined' => 'مرفوض'];

    protected static ?string $model = ChannelAccessRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'طلبات الوصول للقنوات';

    protected static ?string $modelLabel = 'طلب وصول';

    protected static ?string $pluralModelLabel = 'طلبات الوصول للقنوات';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('صاحب الطلب')->relationship('user', 'email')->searchable()->preload()->required(),
            Select::make('channel_id')->label('القناة المطلوبة')->required()->searchable()->preload()->live()
                ->relationship('channel', 'username')
                ->getOptionLabelFromRecordUsing(fn (Channel $record): string => "{$record->platform} · {$record->username}")
                // The account is copied so the request still says what was asked for if the channel goes.
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $channel = Channel::find($state);
                    $set('platform', $channel?->platform);
                    $set('account_id', $channel?->account_id);
                    $set('account_handle', $channel?->username);
                }),
            TextInput::make('platform')->label('المنصة')->required()->readOnly(),
            TextInput::make('account_id')->label('معرّف الحساب')->required()->readOnly(),
            TextInput::make('account_handle')->label('اسم الحساب')->readOnly(),
            Select::make('status')->label('الحالة')->options(self::STATUS_LABELS)->required()->default('pending'),
            Textarea::make('note')->label('ملاحظة')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'channel.user']))
            ->columns([
                TextColumn::make('user.email')->label('صاحب الطلب')->searchable(),
                TextColumn::make('platform')->label('المنصة')->badge()->color('gray'),
                TextColumn::make('account_handle')->label('الحساب')->searchable()->placeholder('—'),
                TextColumn::make('channel.user.email')->label('المساحة المالكة')->placeholder('القناة حُذفت'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'declined' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('decided_at')->label('تاريخ القرار')->dateTime('Y-m-d H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(self::STATUS_LABELS),
                SelectFilter::make('platform')->label('المنصة')
                    ->options(['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'messenger' => 'Messenger']),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChannelAccessRequests::route('/')];
    }
}
