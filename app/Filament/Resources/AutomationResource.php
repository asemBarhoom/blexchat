<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutomationResource\Pages;
use App\Models\Automation;
use App\Models\Channel;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AutomationResource extends Resource
{
    protected static ?string $model = Automation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'الأتمتة';

    protected static ?string $modelLabel = 'أتمتة';

    protected static ?string $pluralModelLabel = 'الأتمتة';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المحتوى';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات الأتمتة')->schema([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                Select::make('channel_id')
                    ->label('القناة')
                    ->relationship('channel', 'account_id')
                    ->getOptionLabelFromRecordUsing(fn (Channel $record) => ucfirst($record->platform).' — '.($record->username ?? $record->account_id))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('trigger_type')
                    ->label('نوع المشغّل')
                    ->options([
                        'comment_keyword' => 'كلمة مفتاحية في تعليق',
                        'dm_keyword'      => 'كلمة مفتاحية في DM',
                        'story_mention'   => 'ذكر في ستوري',
                    ])
                    ->required(),

                TagsInput::make('keywords')
                    ->label('الكلمات المفتاحية')
                    ->placeholder('أضف كلمة واضغط Enter')
                    ->columnSpan(2),

                Toggle::make('active')
                    ->label('مفعّلة')
                    ->default(true),
            ])->columns(2),

            Section::make('الإجراءات')->schema([
                Repeater::make('actions')
                    ->label('')
                    ->schema([
                        Select::make('type')
                            ->label('نوع الإجراء')
                            ->options([
                                'reply_comment'       => 'رد على التعليق',
                                'send_dm'             => 'إرسال رسالة DM',
                                'send_dm_with_button' => 'DM مع زر',
                            ])
                            ->required()
                            ->live(),

                        Textarea::make('message')
                            ->label('نص الرسالة')
                            ->required()
                            ->rows(3),

                        TextInput::make('button_label')
                            ->label('نص الزر')
                            ->visible(fn ($get) => $get('type') === 'send_dm_with_button'),

                        TextInput::make('button_url')
                            ->label('رابط الزر')
                            ->url()
                            ->visible(fn ($get) => $get('type') === 'send_dm_with_button'),
                    ])
                    ->columns(2)
                    ->required()
                    ->minItems(1)
                    ->addActionLabel('إضافة إجراء'),
            ]),
        ]);
    }

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
                        'whatsapp'  => 'success',
                        'tiktok'    => 'gray',
                        'messenger' => 'info',
                        default     => 'secondary',
                    }),

                TextColumn::make('trigger_type')
                    ->label('المشغّل')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => 'تعليق',
                        'dm_keyword'      => 'رسالة مباشرة',
                        'story_mention'   => 'ذكر في ستوري',
                        default           => $state,
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

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel_id')
                    ->label('القناة')
                    ->relationship('channel', 'account_id'),

                SelectFilter::make('trigger_type')
                    ->label('نوع المشغّل')
                    ->options([
                        'comment_keyword' => 'تعليق',
                        'dm_keyword'      => 'رسالة مباشرة',
                        'story_mention'   => 'ذكر في ستوري',
                    ]),

                TernaryFilter::make('active')
                    ->label('الحالة')
                    ->trueLabel('مفعّلة فقط')
                    ->falseLabel('معطّلة فقط')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Automation $record) => $record->active ? 'تعطيل' : 'تفعيل')
                    ->icon(fn (Automation $record) => $record->active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Automation $record) => $record->active ? 'warning' : 'success')
                    ->action(fn (Automation $record) => $record->update(['active' => ! $record->active])),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            InfoSection::make('معلومات الأتمتة')->schema([
                TextEntry::make('name')->label('الاسم'),
                TextEntry::make('channel.platform')
                    ->label('المنصة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? '')),
                TextEntry::make('trigger_type')
                    ->label('المشغّل')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => 'كلمة في تعليق',
                        'dm_keyword'      => 'كلمة في DM',
                        'story_mention'   => 'ذكر في ستوري',
                        default           => $state,
                    }),
                TextEntry::make('keywords')
                    ->label('الكلمات المفتاحية')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(' | ', $state) : $state),
                IconEntry::make('active')
                    ->label('مفعّلة')
                    ->boolean(),
            ])->columns(3),

            InfoSection::make('الإجراءات')->schema([
                RepeatableEntry::make('actions')
                    ->label('')
                    ->schema([
                        TextEntry::make('type')
                            ->label('النوع')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'reply_comment'       => 'رد على التعليق',
                                'send_dm'             => 'DM',
                                'send_dm_with_button' => 'DM مع زر',
                                default               => $state,
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
            'index'  => Pages\ListAutomations::route('/'),
            'create' => Pages\CreateAutomation::route('/create'),
            'view'   => Pages\ViewAutomation::route('/{record}'),
            'edit'   => Pages\EditAutomation::route('/{record}/edit'),
        ];
    }
}
