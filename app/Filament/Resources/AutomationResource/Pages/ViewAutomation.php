<?php

namespace App\Filament\Resources\AutomationResource\Pages;

use App\Filament\Resources\AutomationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAutomation extends ViewRecord
{
    protected static string $resource = AutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Filament shows each of these only in the state it applies to: the trash or live.
            DeleteAction::make()->label('حذف'),
            RestoreAction::make()->label('استرجاع'),
        ];
    }
}
