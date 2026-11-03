<?php

namespace App\Filament\Resources\ChannelAccessRequestResource\Pages;

use App\Filament\Resources\ChannelAccessRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChannelAccessRequests extends ManageRecords
{
    protected static string $resource = ChannelAccessRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة طلب')];
    }
}
