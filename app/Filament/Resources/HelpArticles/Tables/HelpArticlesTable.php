<?php

namespace App\Filament\Resources\HelpArticles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class HelpArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('admin/common.title'))->searchable()->wrap(),
                TextColumn::make('category')->label(__('admin/help.category'))->sortable(),
                TextColumn::make('sort')->label(__('admin/common.sort'))->sortable(),
                ToggleColumn::make('active')->label(__('admin/common.active')),
            ])
            ->filters([
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
