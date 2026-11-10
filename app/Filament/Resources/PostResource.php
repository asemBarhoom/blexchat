<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Filament\Resources\PostResource\RelationManagers;
use App\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function getNavigationLabel(): string
    {
        return __('admin/posts.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/posts.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/posts.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_communication');
    }

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caption')
                    ->label(__('admin/posts.post'))
                    ->limit(60)
                    ->wrap()
                    ->searchable(),

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

                TextColumn::make('media_type')
                    ->label(__('admin/common.media_type'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'image' => __('admin/posts.image'),
                        'video' => __('admin/posts.video'),
                        'carousel' => __('admin/posts.carousel'),
                        'reel' => __('admin/posts.reel'),
                        default => $state ?? '—',
                    }),

                TextColumn::make('comments_count')
                    ->label(__('admin/common.comments'))
                    ->counts('comments')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('posted_at')
                    ->label(__('admin/common.posted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel_id')
                    ->label(__('admin/common.channel'))
                    ->relationship('channel', 'account_id'),

                SelectFilter::make('media_type')
                    ->label(__('admin/common.media_type'))
                    ->options([
                        'image' => __('admin/posts.image'),
                        'video' => __('admin/posts.video'),
                        'carousel' => __('admin/posts.carousel'),
                        'reel' => __('admin/posts.reel'),
                    ]),
            ])
            ->actions([
                ViewAction::make()->label(__('admin/common.view')),
            ])
            ->defaultSort('posted_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/posts.details'))->schema([
                TextEntry::make('caption')->label(__('admin/posts.caption'))->columnSpanFull(),
                TextEntry::make('channel.platform')
                    ->label(__('admin/common.platform'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('media_type')->label(__('admin/common.media_type')),
                TextEntry::make('platform_post_id')->label(__('admin/posts.post_id'))->copyable(),
                TextEntry::make('posted_at')->label(__('admin/common.posted_at'))->dateTime('d/m/Y H:i'),
            ])->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'view' => Pages\ViewPost::route('/{record}'),
        ];
    }
}
