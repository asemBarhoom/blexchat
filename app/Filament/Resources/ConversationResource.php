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

    protected static ?string $navigationLabel = 'المحادثات';

    protected static ?string $modelLabel = 'محادثة';

    protected static ?string $pluralModelLabel = 'المحادثات';

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 3;

    /** @var array<string, string> Who answers the conversation now (Conversation::STATES). */
    public const STATE_LABELS = ['open' => 'بانتظار الرد', 'automation' => 'الأتمتة ترد', 'human' => 'محوّلة لموظف'];

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
                    ->label('المُرسِل')
                    ->searchable()
                    ->description(fn (Conversation $record) => $record->contact_platform_id),

                TextColumn::make('channel.platform')
                    ->label('القناة')
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
                    ->label('من يرد')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATE_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'human' => 'danger',
                        'automation' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('assignee.name')
                    ->label('المسؤول')
                    ->placeholder('غير موزّعة')
                    ->toggleable(),

                IconColumn::make('starred')
                    ->label('مميّزة')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'open' => 'success',
                        'resolved' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open' => 'مفتوحة',
                        'resolved' => 'محلولة',
                        default => $state,
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
                        'open' => 'مفتوحة',
                        'resolved' => 'محلولة',
                    ]),

                SelectFilter::make('state')
                    ->label('من يرد')
                    ->options(self::STATE_LABELS),

                SelectFilter::make('channel_id')
                    ->label('القناة')
                    ->relationship('channel', 'account_id')
                    ->getOptionLabelFromRecordUsing(fn (Channel $record): string => ucfirst($record->platform).' — '.($record->username ?? $record->account_id))
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('assigned_to')
                    ->label('التوزيع')
                    ->nullable()
                    ->trueLabel('موزّعة')
                    ->falseLabel('غير موزّعة'),

                TernaryFilter::make('starred')->label('مميّزة'),
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
                        'open' => 'مفتوحة',
                        'resolved' => 'محلولة',
                        default => $state,
                    }),
                TextEntry::make('unread_count')->label('رسائل غير مقروءة')->numeric(),
                TextEntry::make('last_message_at')->label('آخر نشاط')->dateTime('d/m/Y H:i'),
            ])->columns(3),
            Section::make('صندوق الوارد')->schema([
                TextEntry::make('state')->label('من يرد')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATE_LABELS[$state] ?? $state),
                TextEntry::make('handover_reason')->label('سبب آخر تحويل')->placeholder('—')
                    ->state(fn (Conversation $record): ?string => self::handoverReason($record)),
                TextEntry::make('assignee.name')->label('المسؤول')->placeholder('غير موزّعة'),
                TextEntry::make('remind_at')->label('التذكير')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('labels')->label('الوسوم')->badge()->placeholder('بلا وسوم'),
                TextEntry::make('starred')->label('مميّزة')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا'),
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
