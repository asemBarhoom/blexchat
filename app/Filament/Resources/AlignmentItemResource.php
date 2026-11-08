<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlignmentItemResource\Pages;
use App\Models\AlignmentItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The Figma ↔ API ↔ frontend alignment tracker: every gap found while matching the design,
 * with its owner, priority and status, and the references to find it in each place.
 */
class AlignmentItemResource extends Resource
{
    public const NAVIGATION_GROUP = 'المطابقة';

    protected static ?string $model = AlignmentItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'مطابقة فيغما والـ API';

    protected static ?string $modelLabel = 'بند مطابقة';

    protected static ?string $pluralModelLabel = 'بنود المطابقة';

    protected static string|\UnitEnum|null $navigationGroup = self::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $open = AlignmentItem::where('status', '!=', 'done')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('الرمز')->required()->maxLength(20)->unique(ignoreRecord: true)
                ->helperText('FE- للفرونت، BE- للباك إند، DS- للتصميم.'),
            TextInput::make('title')->label('العنوان')->required()->maxLength(255),
            Select::make('owner')->label('المسؤول')->options(AlignmentItem::OWNERS)->required(),
            Select::make('priority')->label('الأولوية')->options(AlignmentItem::PRIORITIES)->required()->default('p1'),
            Select::make('status')->label('الحالة')->options(AlignmentItem::STATUSES)->required()->default('open'),
            TextInput::make('screen')->label('الشاشة')->maxLength(255),
            TextInput::make('figma_url')->label('رابط فيغما')->url()->maxLength(255),
            TextInput::make('endpoint')->label('الـ endpoint والحقل')->maxLength(255),
            Textarea::make('frontend_ref')->label('مرجع الفرونت')->rows(2)->helperText('ملف ورقم سطر، مثل src/app/liveInbox.ts:118'),
            Textarea::make('backend_ref')->label('مرجع الباك إند')->rows(2),
            Textarea::make('details')->label('التفاصيل')->rows(5),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الرمز')->searchable()->sortable()->badge()->color('gray'),
                TextColumn::make('title')->label('العنوان')->searchable()->wrap()
                    ->description(fn (AlignmentItem $record): ?string => $record->screen),
                TextColumn::make('owner')->label('المسؤول')->badge()
                    ->formatStateUsing(fn (string $state): string => AlignmentItem::OWNERS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'backend' => 'info',
                        'design' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('priority')->label('الأولوية')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => AlignmentItem::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'p0' => 'danger',
                        'p1' => 'warning',
                        default => 'gray',
                    }),
                SelectColumn::make('status')->label('الحالة')->options(AlignmentItem::STATUSES)->selectablePlaceholder(false),
                TextColumn::make('endpoint')->label('الـ endpoint')->toggleable()->wrap()->fontFamily('mono'),
                TextColumn::make('figma_url')->label('فيغما')->placeholder('—')
                    ->formatStateUsing(fn (): string => 'افتح')
                    ->url(fn (AlignmentItem $record): ?string => $record->figma_url, shouldOpenInNewTab: true)
                    ->color('primary'),
            ])
            ->filters([
                SelectFilter::make('owner')->label('المسؤول')->options(AlignmentItem::OWNERS),
                SelectFilter::make('status')->label('الحالة')->options(AlignmentItem::STATUSES),
                SelectFilter::make('priority')->label('الأولوية')->options(AlignmentItem::PRIORITIES),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAlignmentItems::route('/')];
    }
}
