<?php

namespace App\Filament\Resources\MessageDeliveryAttemptResource\Pages;

use App\Filament\Resources\MessageDeliveryAttemptResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageDeliveryAttempts extends ListRecords
{
    protected static string $resource = MessageDeliveryAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
