<?php

namespace App\Filament\Resources\ChannelConnectedScreenResource\Pages;

use App\Filament\Resources\ChannelConnectedScreenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChannelConnectedScreens extends ManageRecords
{
    protected static string $resource = ChannelConnectedScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة شاشة')];
    }
}
