<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Filament\Resources\PostResource\RelationManagers;
use App\Models\Post;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'المنشورات';

    protected static ?string $modelLabel = 'منشور';

    protected static ?string $pluralModelLabel = 'المنشورات';

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caption')
                    ->label('البوست')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('channel.platform')
                    ->label('المنصة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? ''))
                    ->color(fn ($state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp'  => 'success',
                        'tiktok'    => 'gray',
                        'messenger' => 'info',
                        default     => 'secondary',
                    }),

                TextColumn::make('media_type')
                    ->label('نوع الوسائط')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'image'    => 'صورة',
                        'video'    => 'فيديو',
                        'carousel' => 'كاروسيل',
                        'reel'     => 'ريلز',
                        default    => $state ?? '—',
                    }),

                TextColumn::make('comments_count')
                    ->label('التعليقات')
                    ->counts('comments')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('posted_at')
                    ->label('تاريخ النشر')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel_id')
                    ->label('القناة')
                    ->relationship('channel', 'account_id'),

                SelectFilter::make('media_type')
                    ->label('نوع الوسائط')
                    ->options([
                        'image'    => 'صورة',
                        'video'    => 'فيديو',
                        'carousel' => 'كاروسيل',
                        'reel'     => 'ريلز',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            ->defaultSort('posted_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل المنشور')->schema([
                TextEntry::make('caption')->label('النص')->columnSpanFull(),
                TextEntry::make('channel.platform')
                    ->label('المنصة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('media_type')->label('نوع الوسائط'),
                TextEntry::make('platform_post_id')->label('معرّف المنشور')->copyable(),
                TextEntry::make('posted_at')->label('تاريخ النشر')->dateTime('d/m/Y H:i'),
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
            'view'  => Pages\ViewPost::route('/{record}'),
        ];
    }
}
