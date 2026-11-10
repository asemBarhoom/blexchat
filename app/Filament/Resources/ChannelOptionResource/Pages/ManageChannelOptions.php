<?php

namespace App\Filament\Resources\ChannelOptionResource\Pages;

use App\Filament\Resources\ChannelOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChannelOptions extends ManageRecords
{
    protected static string $resource = ChannelOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('admin/channel_options.create'))];
    }
}
