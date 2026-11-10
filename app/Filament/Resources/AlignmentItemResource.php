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
    public static function alignmentGroup(): string
    {
        return __('admin/alignment.alignment');
    }

    protected static ?string $model = AlignmentItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getNavigationLabel(): string
    {
        return __('admin/alignment.figma_and_api_alignment');
    }

    public static function getModelLabel(): string
    {
        return __('admin/alignment.alignment_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/alignment.alignment_items');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return self::alignmentGroup();
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $open = AlignmentItem::where('status', '!=', 'done')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('admin/coupons.code'))->required()->maxLength(20)->unique(ignoreRecord: true)
                ->helperText(__('admin/alignment.fe_for_frontend_be_for_backend_ds_for_de')),
            TextInput::make('title')->label(__('admin/common.title'))->required()->maxLength(255),
            Select::make('owner')->label(__('admin/common.assignee'))->options(AlignmentItem::owners())->required(),
            Select::make('priority')->label(__('admin/alignment.priority'))->options(AlignmentItem::priorities())->required()->default('p1'),
            Select::make('status')->label(__('admin/common.status'))->options(AlignmentItem::statuses())->required()->default('open'),
            TextInput::make('screen')->label(__('admin/alignment.screen'))->maxLength(255),
            TextInput::make('figma_url')->label(__('admin/alignment.figma_link'))->url()->maxLength(255),
            TextInput::make('endpoint')->label(__('admin/alignment.endpoint_and_field'))->maxLength(255),
            Textarea::make('frontend_ref')->label(__('admin/alignment.frontend_reference'))->rows(2)->helperText(__('admin/alignment.a_file_and_line_like_src_app_liveinbox_t')),
            Textarea::make('backend_ref')->label(__('admin/alignment.backend_reference'))->rows(2),
            Textarea::make('details')->label(__('admin/alignment.details'))->rows(5),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('admin/coupons.code'))->searchable()->sortable()->badge()->color('gray'),
                TextColumn::make('title')->label(__('admin/common.title'))->searchable()->wrap()
                    ->description(fn (AlignmentItem $record): ?string => $record->screen),
                TextColumn::make('owner')->label(__('admin/common.assignee'))->badge()
                    ->formatStateUsing(fn (string $state): string => AlignmentItem::owners()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'backend' => 'info',
                        'design' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('priority')->label(__('admin/alignment.priority'))->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => AlignmentItem::priorities()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'p0' => 'danger',
                        'p1' => 'warning',
                        default => 'gray',
                    }),
                SelectColumn::make('status')->label(__('admin/common.status'))->options(AlignmentItem::statuses())->selectablePlaceholder(false),
                TextColumn::make('endpoint')->label(__('admin/alignment.endpoint'))->toggleable()->wrap()->fontFamily('mono'),
                TextColumn::make('figma_url')->label(__('admin/alignment.figma'))->placeholder('—')
                    ->formatStateUsing(fn (): string => __('admin/alignment.open'))
                    ->url(fn (AlignmentItem $record): ?string => $record->figma_url, shouldOpenInNewTab: true)
                    ->color('primary'),
            ])
            ->filters([
                SelectFilter::make('owner')->label(__('admin/common.assignee'))->options(AlignmentItem::owners()),
                SelectFilter::make('status')->label(__('admin/common.status'))->options(AlignmentItem::statuses()),
                SelectFilter::make('priority')->label(__('admin/alignment.priority'))->options(AlignmentItem::priorities()),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAlignmentItems::route('/')];
    }
}
