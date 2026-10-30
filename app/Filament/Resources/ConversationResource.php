<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Filament\Resources\ConversationResource\RelationManagers;
use App\Models\Conversation;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'المحادثات';

    protected static ?string $modelLabel = 'محادثة';

    protected static ?string $pluralModelLabel = 'المحادثات';

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact_name')
                    ->label('المُرسِل')
                    ->searchable()
                    ->description(fn (Conversation $record) => $record->contact_platform_id),

                TextColumn::make('channel.platform')
                    ->label('القناة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? ''))
                    ->color(fn ($state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp'  => 'success',
                        'tiktok'    => 'gray',
                        'messenger' => 'info',
                        default     => 'secondary',
                    }),

                TextColumn::make('last_message')
                    ->label('آخر رسالة')
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('unread_count')
                    ->label('غير مقروءة')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'open'     => 'success',
                        'resolved' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open'     => 'مفتوحة',
                        'resolved' => 'محلولة',
                        default    => $state,
                    }),

                TextColumn::make('last_message_at')
                    ->label('آخر نشاط')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'open'     => 'مفتوحة',
                        'resolved' => 'محلولة',
                    ]),

                SelectFilter::make('channel_id')
                    ->label('القناة')
                    ->relationship('channel', 'account_id'),
            ])
            ->actions([
                ViewAction::make()->label('عرض'),
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات المحادثة')->schema([
                TextEntry::make('contact_name')->label('المُرسِل'),
                TextEntry::make('contact_platform_id')->label('معرّف المنصة'),
                TextEntry::make('channel.platform')
                    ->label('القناة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open'     => 'مفتوحة',
                        'resolved' => 'محلولة',
                        default    => $state,
                    }),
                TextEntry::make('unread_count')->label('رسائل غير مقروءة')->numeric(),
                TextEntry::make('last_message_at')->label('آخر نشاط')->dateTime('d/m/Y H:i'),
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
            'view'  => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
