<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Filament\Resources\ChannelResource\RelationManagers;
use App\Models\Channel;
use App\Models\Workspace;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    public static function getNavigationLabel(): string
    {
        return __('admin/common.channels');
    }

    public static function getModelLabel(): string
    {
        return __('admin/channels.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/common.channels');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_content');
    }

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['workspace', 'user']))
            ->columns([
                TextColumn::make('workspace.name')->label(__('admin/common.workspace'))->searchable()->toggleable(),
                TextColumn::make('platform')
                    ->label(__('admin/common.platform'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp' => 'success',
                        'tiktok' => 'gray',
                        'messenger' => 'info',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('username')
                    ->label(__('admin/channels.username'))
                    ->default('—')
                    ->searchable(),

                TextColumn::make('account_id')
                    ->label(__('admin/channels.account_id'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label(__('admin/common.owner'))
                    ->searchable(),

                TextColumn::make('followers')
                    ->label(__('admin/channels.followers'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'expired' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => __('admin/channels.active'),
                        'inactive' => __('admin/channels.inactive'),
                        'expired' => __('admin/channels.expired'),
                        default => $state,
                    }),

                TextColumn::make('connected_at')
                    ->label(__('admin/channels.connected_at'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('workspace_id')->label(__('admin/common.workspace'))->options(fn (): array => Workspace::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('platform')
                    ->label(__('admin/common.platform'))
                    ->options([
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp',
                        'tiktok' => 'TikTok',
                        'messenger' => 'Messenger',
                    ]),

                SelectFilter::make('status')
                    ->label(__('admin/common.status'))
                    ->options([
                        'active' => __('admin/channels.active'),
                        'inactive' => __('admin/channels.inactive'),
                        'expired' => __('admin/channels.expired'),
                    ]),
            ])
            ->actions([
                ViewAction::make()->label(__('admin/common.view')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('admin/common.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/channels.details'))->schema([
                TextEntry::make('platform')
                    ->label(__('admin/common.platform'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextEntry::make('username')->label(__('admin/channels.username')),
                TextEntry::make('account_id')->label(__('admin/channels.account_id'))->copyable(),
                TextEntry::make('user.name')->label(__('admin/common.owner')),
                TextEntry::make('followers')->label(__('admin/channels.followers'))->numeric(),

                TextEntry::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => __('admin/channels.active'),
                        'inactive' => __('admin/channels.inactive'),
                        'expired' => __('admin/channels.expired'),
                        default => $state,
                    }),

                TextEntry::make('connected_at')->label(__('admin/channels.connected_at'))->dateTime('d/m/Y H:i'),
            ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ConversationsRelationManager::class,
            RelationManagers\AutomationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannels::route('/'),
            'view' => Pages\ViewChannel::route('/{record}'),
        ];
    }
}
