<?php

namespace App\Filament\Resources\ChannelConnectScreenResource\Pages;

use App\Filament\Resources\ChannelConnectScreenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChannelConnectScreen extends EditRecord
{
    protected static string $resource = ChannelConnectScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label(__('admin/common.delete')),
        ];
    }
}
