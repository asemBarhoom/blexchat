<?php

namespace App\Filament\Resources\PricingCompareRowResource\Pages;

use App\Filament\Resources\PricingCompareRowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingCompareRow extends EditRecord
{
    protected static string $resource = PricingCompareRowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label(__('admin/common.delete')),
        ];
    }
}
