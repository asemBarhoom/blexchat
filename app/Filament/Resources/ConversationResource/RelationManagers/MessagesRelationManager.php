<?php

namespace App\Filament\Resources\ConversationResource\RelationManagers;

use App\Models\Message;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'الرسائل';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('direction')
                    ->label('الاتجاه')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inbound' => 'وارد',
                        Message::DIRECTION_SYSTEM => 'النظام',
                        default => 'صادر',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inbound' => 'info',
                        Message::DIRECTION_SYSTEM => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'text' => 'نص',
                        'text_with_button' => 'نص + زر',
                        Message::TYPE_HANDOVER => 'تحويل لموظف',
                        Message::TYPE_COMMENT => 'تعليق',
                        default => $state,
                    }),

                // A handover keeps its reason key as content; the AI's own reason, when it gave one, is the note.
                TextColumn::make('content')->label('المحتوى')->limit(60)->wrap()
                    ->formatStateUsing(fn (Message $record, ?string $state): ?string => $record->type === Message::TYPE_HANDOVER
                        ? ($record->note ?? __("inbox.handover.{$state}"))
                        : $state),

                IconColumn::make('automated')
                    ->label('تلقائية')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('by_ai')
                    ->label('Blexchat AI')
                    ->boolean()
                    ->trueIcon('heroicon-o-sparkles')
                    ->trueColor('primary')
                    ->falseColor('gray'),

                TextColumn::make('sent_at')->label('وقت الإرسال')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('sent_at', 'asc');
    }
}
