<?php

namespace App\Filament\Resources\FirstMoveResource\Pages;

use App\Filament\Resources\FirstMoveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFirstMoves extends ManageRecords
{
    protected static string $resource = FirstMoveResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('admin/first_moves.create'))];
    }
}
