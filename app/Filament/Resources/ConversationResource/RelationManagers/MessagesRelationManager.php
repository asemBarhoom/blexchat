<?php

namespace App\Filament\Resources\ConversationResource\RelationManagers;

use App\Models\Message;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/conversations.messages');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('direction')
                    ->label(__('admin/conversations.direction'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inbound' => __('admin/conversations.inbound'),
                        Message::DIRECTION_SYSTEM => __('admin/conversations.system'),
                        default => __('admin/conversations.outbound'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inbound' => 'info',
                        Message::DIRECTION_SYSTEM => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('type')
                    ->label(__('admin/common.type'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'text' => __('admin/conversations.text'),
                        'text_with_button' => __('admin/conversations.text_with_button'),
                        Message::TYPE_HANDOVER => __('admin/conversations.handover'),
                        Message::TYPE_COMMENT => __('admin/common.comment'),
                        default => $state,
                    }),

                // A handover keeps its reason key as content; the AI's own reason, when it gave one, is the note.
                TextColumn::make('content')->label(__('admin/common.content'))->limit(60)->wrap()
                    ->formatStateUsing(fn (Message $record, ?string $state): ?string => $record->type === Message::TYPE_HANDOVER
                        ? ($record->note ?? __("inbox.handover.{$state}"))
                        : $state),

                IconColumn::make('automated')
                    ->label(__('admin/conversations.automated'))
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('by_ai')
                    ->label('Blexchat AI')
                    ->boolean()
                    ->trueIcon('heroicon-o-sparkles')
                    ->trueColor('primary')
                    ->falseColor('gray'),

                TextColumn::make('sent_at')->label(__('admin/conversations.sent_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('sent_at', 'asc');
    }
}
