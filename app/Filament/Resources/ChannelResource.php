<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Filament\Resources\ChannelResource\RelationManagers;
use App\Models\Channel;
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

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'القنوات';

    protected static ?string $modelLabel = 'قناة';

    protected static ?string $pluralModelLabel = 'القنوات';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المحتوى';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->label('المنصة')
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
                    ->label('اسم الحساب')
                    ->default('—')
                    ->searchable(),

                TextColumn::make('account_id')
                    ->label('معرّف الحساب')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('صاحب الحساب')
                    ->searchable(),

                TextColumn::make('followers')
                    ->label('المتابعون')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'expired' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'expired' => 'منتهي الصلاحية',
                        default => $state,
                    }),

                TextColumn::make('connected_at')
                    ->label('تاريخ الربط')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('المنصة')
                    ->options([
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp',
                        'tiktok' => 'TikTok',
                        'messenger' => 'Messenger',
                    ]),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'expired' => 'منتهي الصلاحية',
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('عرض'),
                DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات القناة')->schema([
                TextEntry::make('platform')
                    ->label('المنصة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextEntry::make('username')->label('اسم الحساب'),
                TextEntry::make('account_id')->label('معرّف الحساب')->copyable(),
                TextEntry::make('user.name')->label('صاحب الحساب'),
                TextEntry::make('followers')->label('المتابعون')->numeric(),

                TextEntry::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'expired' => 'منتهي الصلاحية',
                        default => $state,
                    }),

                TextEntry::make('connected_at')->label('تاريخ الربط')->dateTime('d/m/Y H:i'),
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
