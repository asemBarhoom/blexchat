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
    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return ['pending' => __('admin/common.pending'), 'approved' => __('admin/access_requests.approved'), 'declined' => __('admin/access_requests.declined')];
    }

    protected static ?string $model = ChannelAccessRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public static function getNavigationLabel(): string
    {
        return __('admin/access_requests.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/access_requests.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/access_requests.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label(__('admin/access_requests.requester'))->relationship('user', 'email')->searchable()->preload()->required(),
            Select::make('channel_id')->label(__('admin/access_requests.channel'))->required()->searchable()->preload()->live()
                ->relationship('channel', 'username')
                ->getOptionLabelFromRecordUsing(fn (Channel $record): string => "{$record->platform} · {$record->username}")
                // The account is copied so the request still says what was asked for if the channel goes.
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $channel = Channel::find($state);
                    $set('platform', $channel?->platform);
                    $set('account_id', $channel?->account_id);
                    $set('account_handle', $channel?->username);
                }),
            TextInput::make('platform')->label(__('admin/common.platform'))->required()->readOnly(),
            TextInput::make('account_id')->label(__('admin/common.account_id'))->required()->readOnly(),
            TextInput::make('account_handle')->label(__('admin/common.username'))->readOnly(),
            Select::make('status')->label(__('admin/common.status'))->options(self::statusLabels())->required()->default('pending'),
            Textarea::make('note')->label(__('admin/common.note'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'channel.user']))
            ->columns([
                TextColumn::make('user.email')->label(__('admin/access_requests.requester'))->searchable(),
                TextColumn::make('platform')->label(__('admin/common.platform'))->badge()->color('gray'),
                TextColumn::make('account_handle')->label(__('admin/common.account'))->searchable()->placeholder('—'),
                TextColumn::make('channel.user.email')->label(__('admin/access_requests.owner'))->placeholder(__('admin/access_requests.channel_deleted')),
                TextColumn::make('status')->label(__('admin/common.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'declined' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')->label(__('admin/access_requests.created_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('decided_at')->label(__('admin/access_requests.decided_at'))->dateTime('Y-m-d H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('admin/common.status'))->options(self::statusLabels()),
                SelectFilter::make('platform')->label(__('admin/common.platform'))
                    ->options(['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'messenger' => 'Messenger']),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin/common.view')),
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChannelAccessRequests::route('/')];
    }
}
