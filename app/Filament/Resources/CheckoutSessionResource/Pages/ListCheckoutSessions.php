<?php

namespace App\Filament\Resources\CheckoutSessionResource\Pages;

use App\Filament\Resources\CheckoutSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListCheckoutSessions extends ListRecords
{
    protected static string $resource = CheckoutSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
