<?php

namespace App\Filament\Resources\ComparisonPointResource\Pages;

use App\Filament\Resources\ComparisonPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageComparisonPoints extends ManageRecords
{
    protected static string $resource = ComparisonPointResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة نقطة')];
    }
}
