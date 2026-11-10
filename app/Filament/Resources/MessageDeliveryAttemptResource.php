<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageDeliveryAttemptResource\Pages;
use App\Models\MessageDeliveryAttempt;
use App\Services\Channels\DeliveryRetrier;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MessageDeliveryAttemptResource extends Resource
{
    protected static ?string $model = MessageDeliveryAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    public static function getNavigationLabel(): string
    {
        return __('admin/delivery_attempts.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/delivery_attempts.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/delivery_attempts.navigation');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_communication');
    }

    protected static ?int $navigationSort = 4;

    /** The failed sends still waiting for someone to retry them. */
    public static function getNavigationBadge(): ?string
    {
        $failed = MessageDeliveryAttempt::where('status', 'failed')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['conversation.channel.user', 'conversation.channel.workspace']))
            ->columns([
                TextColumn::make('conversation.channel.platform')
                    ->label(__('admin/common.platform'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? ''))
                    ->color(fn ($state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp' => 'success',
                        'tiktok' => 'gray',
                        'messenger' => 'info',
                        default => 'secondary',
                    }),

                TextColumn::make('conversation.channel.username')
                    ->label(__('admin/common.channel'))
                    ->placeholder('—'),

                TextColumn::make('conversation.channel.user.name')
                    ->label(__('admin/common.owner')),

                TextColumn::make('conversation.channel.workspace.name')
                    ->label(__('admin/delivery_attempts.workspace'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('content')
                    ->label(__('admin/common.text'))
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'sent' ? 'success' : 'danger'),

                TextColumn::make('error_code')
                    ->label(__('admin/delivery_attempts.failure'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => self::errorLabels()[$state] ?? $state)
                    ->color('danger'),

                TextColumn::make('attempts')
                    ->label(__('admin/delivery_attempts.attempts'))
                    ->numeric(),

                TextColumn::make('last_attempt_at')
                    ->label(__('admin/delivery_attempts.last_attempt_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label(__('admin/common.platform'))
                    ->options(self::platforms())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('conversation.channel', fn (Builder $channel): Builder => $channel->where('platform', $data['value']))
                        : $query),

                SelectFilter::make('status')
                    ->label(__('admin/common.status'))
                    ->options(self::statusLabels())
                    ->default('failed'),

                SelectFilter::make('error_code')
                    ->label(__('admin/delivery_attempts.failure'))
                    ->options(self::errorLabels()),

                Filter::make('period')
                    ->label(__('admin/delivery_attempts.period'))
                    ->schema([
                        DatePicker::make('from')->label(__('admin/delivery_attempts.from')),
                        DatePicker::make('until')->label(__('admin/delivery_attempts.until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $period, string $date): Builder => $period->whereDate('last_attempt_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $period, string $date): Builder => $period->whereDate('last_attempt_at', '<=', $date))),
            ])
            ->actions([
                ViewAction::make()->label(__('admin/common.view')),
                self::retryAction(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    self::retryBulkAction(),
                ]),
            ])
            ->defaultSort('last_attempt_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/delivery_attempts.details'))->schema([
                TextEntry::make('conversation.channel.platform')
                    ->label(__('admin/common.platform'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('conversation.channel.username')->label(__('admin/common.channel'))->placeholder('—'),
                TextEntry::make('conversation.channel.user.name')->label(__('admin/common.owner')),
                TextEntry::make('conversation.channel.workspace.name')->label(__('admin/delivery_attempts.workspace'))->placeholder('—'),
                TextEntry::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state),
                TextEntry::make('error_code')
                    ->label(__('admin/delivery_attempts.failure'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => self::errorLabels()[$state] ?? $state),
                TextEntry::make('attempts')->label(__('admin/delivery_attempts.attempts'))->numeric(),
                TextEntry::make('last_attempt_at')->label(__('admin/delivery_attempts.last_attempt_at'))->dateTime('d/m/Y H:i'),
                TextEntry::make('conversation_id')
                    ->label(__('admin/delivery_attempts.conversation'))
                    ->formatStateUsing(fn (int $state): string => '#'.$state)
                    ->url(fn (MessageDeliveryAttempt $record): string => ConversationResource::getUrl('view', ['record' => $record->conversation_id])),
            ])->columns(3),
            Section::make(__('admin/delivery_attempts.content'))->schema([
                TextEntry::make('content')->label(__('admin/common.text')),
                TextEntry::make('error_message')->label(__('admin/delivery_attempts.error_message'))->placeholder('—'),
            ]),
        ]);
    }

    /** "Retry" on one row: the same service the API calls. */
    public static function retryAction(): Action
    {
        return Action::make('retry')
            ->label(__('admin/delivery_attempts.retry'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('admin/delivery_attempts.retry_heading'))
            ->modalDescription(__('admin/delivery_attempts.retry_note'))
            ->hidden(fn (MessageDeliveryAttempt $record): bool => $record->status === 'sent')
            ->disabled(fn (MessageDeliveryAttempt $record): bool => $record->error_code === 'channel_not_live')
            ->action(function (MessageDeliveryAttempt $record, DeliveryRetrier $retrier): void {
                $result = $retrier->retry($record);

                if ($result->alreadySent) {
                    Notification::make()->title(__('admin/delivery_attempts.retry_already_sent'))->info()->send();

                    return;
                }

                if ($result->sent) {
                    Notification::make()->title(__('admin/delivery_attempts.retry_sent'))->success()->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin/delivery_attempts.retry_failed'))
                    ->body(self::errorLabels()[$result->errorCode] ?? $result->errorCode)
                    ->danger()
                    ->send();
            });
    }

    /** Retries every eligible row at once and reports how many went through. */
    public static function retryBulkAction(): BulkAction
    {
        return BulkAction::make('retrySelected')
            ->label(__('admin/delivery_attempts.retry_selected'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('admin/delivery_attempts.retry_selected_heading'))
            ->modalDescription(__('admin/delivery_attempts.retry_selected_note'))
            ->action(function (Collection $records, DeliveryRetrier $retrier): void {
                $sent = 0;
                $failed = 0;

                foreach ($records as $attempt) {
                    if ($attempt->status === 'sent' || $attempt->error_code === 'channel_not_live') {
                        continue;
                    }

                    $retrier->retry($attempt)->sent ? $sent++ : $failed++;
                }

                $notification = Notification::make()
                    ->title(__('admin/delivery_attempts.bulk_result', ['sent' => $sent, 'failed' => $failed]));

                $failed > 0 ? $notification->warning() : $notification->success();

                $notification->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'failed' => __('admin/delivery_attempts.failed'),
            'sent' => __('admin/delivery_attempts.sent'),
        ];
    }

    /** @return array<string, string> The codes the API stores on a failed attempt. */
    public static function errorLabels(): array
    {
        return [
            'provider_error' => __('admin/delivery_attempts.error_provider_error'),
            'channel_not_live' => __('admin/delivery_attempts.error_channel_not_live'),
        ];
    }

    /** @return array<string, string> */
    public static function platforms(): array
    {
        return ['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'messenger' => 'Messenger'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageDeliveryAttempts::route('/'),
            'view' => Pages\ViewMessageDeliveryAttempt::route('/{record}'),
        ];
    }
}
