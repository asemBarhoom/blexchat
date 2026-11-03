<?php

namespace App\Filament\Resources\PricingCompareGroupResource\Pages;

use App\Filament\Resources\PricingCompareGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePricingCompareGroups extends ManageRecords
{
    protected static string $resource = PricingCompareGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة مجموعة')];
    }
}
