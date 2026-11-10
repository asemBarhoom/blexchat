<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Filament\Resources\ConversationResource\RelationManagers;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getNavigationLabel(): string
    {
        return __('admin/common.conversations');
    }

    public static function getModelLabel(): string
    {
        return __('admin/conversations.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/common.conversations');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_communication');
    }

    protected static ?int $navigationSort = 3;

    /** @return array<string, string> Who answers the conversation now (Conversation::STATES). */
    public static function stateLabels(): array
    {
        return ['open' => __('admin/conversations.state_open'), 'automation' => __('admin/conversations.state_automation'), 'human' => __('admin/conversations.state_human')];
    }

    /**
     * Why the conversation was last handed over to a person, from its latest handover message.
     */
    public static function handoverReason(Conversation $conversation): ?string
    {
        $handover = $conversation->messages()->where('type', Message::TYPE_HANDOVER)->latest('sent_at')->first();

        return $handover === null ? null : ($handover->note ?? __("inbox.handover.{$handover->content}"));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['channel', 'assignee']))
            ->columns([
                TextColumn::make('contact_name')
                    ->label(__('admin/common.sender'))
                    ->searchable()
                    ->description(fn (Conversation $record) => $record->contact_platform_id),

                TextColumn::make('channel.platform')
                    ->label(__('admin/common.channel'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? ''))
                    ->color(fn ($state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp' => 'success',
                        'tiktok' => 'gray',
                        'messenger' => 'info',
                        default => 'secondary',
                    }),

                TextColumn::make('state')
                    ->label(__('admin/common.answered_by'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::stateLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'human' => 'danger',
                        'automation' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('assignee.name')
                    ->label(__('admin/common.assignee'))
                    ->placeholder(__('admin/common.unassigned'))
                    ->toggleable(),

                IconColumn::make('starred')
                    ->label(__('admin/common.starred'))
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_message')
                    ->label(__('admin/common.last_message'))
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('unread_count')
                    ->label(__('admin/conversations.unread'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'open' => 'success',
                        'resolved' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open' => __('admin/common.open'),
                        'resolved' => __('admin/common.resolved'),
                        default => $state,
                    }),

                TextColumn::make('last_message_at')
                    ->label(__('admin/common.last_active'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin/common.status'))
                    ->options([
                        'open' => __('admin/common.open'),
                        'resolved' => __('admin/common.resolved'),
                    ]),

                SelectFilter::make('state')
                    ->label(__('admin/common.answered_by'))
                    ->options(self::stateLabels()),

                SelectFilter::make('channel_id')
                    ->label(__('admin/common.channel'))
                    ->relationship('channel', 'account_id')
                    ->getOptionLabelFromRecordUsing(fn (Channel $record): string => ucfirst($record->platform).' — '.($record->username ?? $record->account_id))
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('assigned_to')
                    ->label(__('admin/conversations.assignment'))
                    ->nullable()
                    ->trueLabel(__('admin/conversations.assigned'))
                    ->falseLabel(__('admin/common.unassigned')),

                TernaryFilter::make('starred')->label(__('admin/common.starred')),
            ])
            ->actions([
                ViewAction::make()->label(__('admin/common.view')),
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/conversations.details'))->schema([
                TextEntry::make('contact_name')->label(__('admin/common.sender')),
                TextEntry::make('contact_platform_id')->label(__('admin/common.platform_id')),
                TextEntry::make('channel.platform')
                    ->label(__('admin/common.channel'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open' => __('admin/common.open'),
                        'resolved' => __('admin/common.resolved'),
                        default => $state,
                    }),
                TextEntry::make('unread_count')->label(__('admin/conversations.unread_messages'))->numeric(),
                TextEntry::make('last_message_at')->label(__('admin/common.last_active'))->dateTime('d/m/Y H:i'),
            ])->columns(3),
            Section::make(__('admin/conversations.inbox'))->schema([
                TextEntry::make('state')->label(__('admin/common.answered_by'))->badge()
                    ->formatStateUsing(fn (string $state): string => self::stateLabels()[$state] ?? $state),
                TextEntry::make('handover_reason')->label(__('admin/conversations.handover_reason'))->placeholder('—')
                    ->state(fn (Conversation $record): ?string => self::handoverReason($record)),
                TextEntry::make('assignee.name')->label(__('admin/common.assignee'))->placeholder(__('admin/common.unassigned')),
                TextEntry::make('remind_at')->label(__('admin/conversations.reminder'))->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('labels')->label(__('admin/common.tags'))->badge()->placeholder(__('admin/common.no_tags')),
                TextEntry::make('starred')->label(__('admin/common.starred'))
                    ->formatStateUsing(fn (bool $state): string => $state ? __('admin/common.yes') : __('admin/common.no')),
            ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
