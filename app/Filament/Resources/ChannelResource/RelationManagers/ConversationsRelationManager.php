<?php

namespace App\Filament\Resources\ChannelResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ConversationsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/common.conversations');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact_name')->label(__('admin/common.sender')),
                TextColumn::make('last_message')->label(__('admin/common.last_message'))->limit(40),
                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open' => __('admin/common.open'),
                        'resolved' => __('admin/common.resolved'),
                        default => $state,
                    })
                    ->color(fn ($state) => $state === 'open' ? 'success' : 'gray'),
                TextColumn::make('last_message_at')->label(__('admin/common.last_active'))->since(),
            ])
            ->defaultSort('last_message_at', 'desc');
    }
}
