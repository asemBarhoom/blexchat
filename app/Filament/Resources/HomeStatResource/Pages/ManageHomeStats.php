<?php

namespace App\Filament\Resources\HomeStatResource\Pages;

use App\Filament\Resources\HomeStatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHomeStats extends ManageRecords
{
    protected static string $resource = HomeStatResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة رقم')];
    }
}
