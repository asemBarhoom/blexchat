<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutomationResource\Pages;
use App\Models\Automation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AutomationResource extends Resource
{
    protected static ?string $model = Automation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    public static function getNavigationLabel(): string
    {
        return __('admin/common.automations');
    }

    public static function getModelLabel(): string
    {
        return __('admin/automations.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/common.automations');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_content');
    }

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('channel.platform')
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

                TextColumn::make('trigger_type')
                    ->label(__('admin/common.trigger'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => __('admin/common.comment'),
                        'dm_keyword' => __('admin/automations.dm'),
                        'story_mention' => __('admin/automations.story_mention'),
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('keywords')
                    ->label(__('admin/common.keywords'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->limit(40)
                    ->toggleable(),

                IconColumn::make('active')
                    ->label(__('admin/common.active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('ai_enabled')->label('AI')->boolean(),

                TextColumn::make('folder.name')->label(__('admin/automations.folder'))->placeholder('—')->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('admin/automations.created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()->label(__('admin/automations.trashed')),
                SelectFilter::make('channel_id')
                    ->label(__('admin/common.channel'))
                    ->relationship('channel', 'account_id'),

                SelectFilter::make('trigger_type')
                    ->label(__('admin/automations.trigger_type'))
                    ->options([
                        'comment_keyword' => __('admin/common.comment'),
                        'dm_keyword' => __('admin/automations.dm'),
                        'story_mention' => __('admin/automations.story_mention'),
                    ]),

                TernaryFilter::make('active')
                    ->label(__('admin/common.status'))
                    ->trueLabel(__('admin/automations.active_only'))
                    ->falseLabel(__('admin/automations.inactive_only'))
                    ->native(false),
            ])
            ->actions([
                ViewAction::make()->label(__('admin/common.view')),
                Action::make('toggle')
                    ->label(fn (Automation $record) => $record->active ? __('admin/automations.turn_off') : __('admin/automations.turn_on'))
                    ->icon(fn (Automation $record) => $record->active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Automation $record) => $record->active ? 'warning' : 'success')
                    ->action(fn (Automation $record) => $record->update(['active' => ! $record->active])),
                DeleteAction::make()->label(__('admin/common.delete'))
                    ->modalDescription(__('admin/automations.delete_note')),
                RestoreAction::make()->label(__('admin/common.restore')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('admin/common.delete_selected'))
                        ->modalDescription(__('admin/automations.bulk_delete_note')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/automations.details'))->schema([
                TextEntry::make('name')->label(__('admin/common.name')),
                TextEntry::make('channel.platform')
                    ->label(__('admin/common.platform'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('trigger_type')
                    ->label(__('admin/common.trigger'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => __('admin/automations.comment_keyword'),
                        'dm_keyword' => __('admin/automations.dm_keyword'),
                        'story_mention' => __('admin/automations.story_mention'),
                        default => $state,
                    }),
                TextEntry::make('keywords')
                    ->label(__('admin/common.keywords'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(' | ', $state) : $state),
                IconEntry::make('active')
                    ->label(__('admin/common.active'))
                    ->boolean(),
            ])->columns(3),

            Section::make(__('admin/automations.actions'))->schema([
                RepeatableEntry::make('actions')
                    ->label('')
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('admin/common.type'))
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'reply_comment' => __('admin/automations.reply_comment'),
                                'send_dm' => 'DM',
                                'send_dm_with_button' => __('admin/automations.dm_with_button'),
                                default => $state,
                            })
                            ->badge(),
                        TextEntry::make('message')->label(__('admin/common.message')),
                        TextEntry::make('button_label')->label(__('admin/common.button_text'))->default('—'),
                        TextEntry::make('button_url')->label(__('admin/automations.button_url'))->default('—'),
                    ])
                    ->columns(4),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomations::route('/'),
            'view' => Pages\ViewAutomation::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
