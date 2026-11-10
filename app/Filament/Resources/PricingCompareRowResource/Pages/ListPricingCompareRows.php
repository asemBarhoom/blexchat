<?php

namespace App\Filament\Resources\PricingCompareRowResource\Pages;

use App\Filament\Resources\PricingCompareRowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingCompareRows extends ListRecords
{
    protected static string $resource = PricingCompareRowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin/pricing.add_row')),
        ];
    }
}
