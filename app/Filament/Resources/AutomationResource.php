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

    protected static ?string $navigationLabel = 'الأتمتة';

    protected static ?string $modelLabel = 'أتمتة';

    protected static ?string $pluralModelLabel = 'الأتمتة';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المحتوى';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('channel.platform')
                    ->label('المنصة')
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
                    ->label('المشغّل')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => 'تعليق',
                        'dm_keyword' => 'رسالة مباشرة',
                        'story_mention' => 'ذكر في ستوري',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('keywords')
                    ->label('الكلمات المفتاحية')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->limit(40)
                    ->toggleable(),

                IconColumn::make('active')
                    ->label('مفعّلة')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('ai_enabled')->label('AI')->boolean(),

                TextColumn::make('folder.name')->label('المجلد')->placeholder('—')->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()->label('المحذوفة'),
                SelectFilter::make('channel_id')
                    ->label('القناة')
                    ->relationship('channel', 'account_id'),

                SelectFilter::make('trigger_type')
                    ->label('نوع المشغّل')
                    ->options([
                        'comment_keyword' => 'تعليق',
                        'dm_keyword' => 'رسالة مباشرة',
                        'story_mention' => 'ذكر في ستوري',
                    ]),

                TernaryFilter::make('active')
                    ->label('الحالة')
                    ->trueLabel('مفعّلة فقط')
                    ->falseLabel('معطّلة فقط')
                    ->native(false),
            ])
            ->actions([
                ViewAction::make()->label('عرض'),
                Action::make('toggle')
                    ->label(fn (Automation $record) => $record->active ? 'تعطيل' : 'تفعيل')
                    ->icon(fn (Automation $record) => $record->active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Automation $record) => $record->active ? 'warning' : 'success')
                    ->action(fn (Automation $record) => $record->update(['active' => ! $record->active])),
                DeleteAction::make()->label('حذف')
                    ->modalDescription('تنتقل الأتمتة إلى سلة المحذوفات، وتُحذف نهائياً بعد 30 يوماً.'),
                RestoreAction::make()->label('استرجاع'),
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
            Section::make('معلومات الأتمتة')->schema([
                TextEntry::make('name')->label('الاسم'),
                TextEntry::make('channel.platform')
                    ->label('المنصة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('trigger_type')
                    ->label('المشغّل')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => 'كلمة في تعليق',
                        'dm_keyword' => 'كلمة في DM',
                        'story_mention' => 'ذكر في ستوري',
                        default => $state,
                    }),
                TextEntry::make('keywords')
                    ->label('الكلمات المفتاحية')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(' | ', $state) : $state),
                IconEntry::make('active')
                    ->label('مفعّلة')
                    ->boolean(),
            ])->columns(3),

            Section::make('الإجراءات')->schema([
                RepeatableEntry::make('actions')
                    ->label('')
                    ->schema([
                        TextEntry::make('type')
                            ->label('النوع')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'reply_comment' => 'رد على التعليق',
                                'send_dm' => 'DM',
                                'send_dm_with_button' => 'DM مع زر',
                                default => $state,
                            })
                            ->badge(),
                        TextEntry::make('message')->label('الرسالة'),
                        TextEntry::make('button_label')->label('نص الزر')->default('—'),
                        TextEntry::make('button_url')->label('رابط الزر')->default('—'),
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
