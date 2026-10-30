<?php

namespace App\Filament\Resources\ChannelResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConversationsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversations';

    protected static ?string $title = 'المحادثات';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact_name')->label('المُرسِل'),
                TextColumn::make('last_message')->label('آخر رسالة')->limit(40),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open'     => 'مفتوحة',
                        'resolved' => 'محلولة',
                        default    => $state,
                    })
                    ->color(fn ($state) => $state === 'open' ? 'success' : 'gray'),
                TextColumn::make('last_message_at')->label('آخر نشاط')->since(),
            ])
            ->defaultSort('last_message_at', 'desc');
    }
}
