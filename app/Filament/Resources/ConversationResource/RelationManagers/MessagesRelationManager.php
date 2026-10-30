<?php

namespace App\Filament\Resources\ConversationResource\RelationManagers;

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
                    ->formatStateUsing(fn ($state) => $state === 'inbound' ? 'وارد' : 'صادر')
                    ->color(fn ($state) => $state === 'inbound' ? 'info' : 'success'),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'text'             => 'نص',
                        'text_with_button' => 'نص + زر',
                        default            => $state,
                    }),

                TextColumn::make('content')->label('المحتوى')->limit(60)->wrap(),

                IconColumn::make('automated')
                    ->label('تلقائية')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('sent_at')->label('وقت الإرسال')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('sent_at', 'asc');
    }
}
