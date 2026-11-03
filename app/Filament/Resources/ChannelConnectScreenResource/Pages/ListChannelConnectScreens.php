<?php

namespace App\Filament\Resources\ChannelConnectScreenResource\Pages;

use App\Filament\Resources\ChannelConnectScreenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChannelConnectScreens extends ListRecords
{
    protected static string $resource = ChannelConnectScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة شاشة ربط'),
        ];
    }
}
