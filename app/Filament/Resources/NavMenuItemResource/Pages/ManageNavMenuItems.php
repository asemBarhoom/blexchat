<?php

namespace App\Filament\Resources\NavMenuItemResource\Pages;

use App\Filament\Resources\NavMenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNavMenuItems extends ManageRecords
{
    protected static string $resource = NavMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة عنصر')];
    }
}
