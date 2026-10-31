<?php

namespace App\Filament\Support;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;

/**
 * What every admin-managed homepage list shares: drag ordering, an on/off switch,
 * edit and delete in a modal, and images uploaded to the public disk.
 */
final class HomeContentFields
{
    public const NAVIGATION_GROUP = 'الصفحة الرئيسية للموقع';

    /**
     * @param  list<Column>  $columns
     * @param  list<BaseFilter>  $filters
     */
    public static function table(Table $table, array $columns, array $filters = []): Table
    {
        return $table
            ->columns([...$columns, ToggleColumn::make('active')->label('ظاهر')])
            ->filters($filters)
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->label('حذف المحدد')]),
            ]);
    }

    public static function active(): Toggle
    {
        return Toggle::make('active')->label('ظاهر في الموقع')->default(true);
    }

    public static function image(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory('marketing/home/uploads')
            ->visibility('public')
            ->required();
    }
}
