<?php

namespace App\Filament\Resources\UseCaseResource\Pages;

use App\Filament\Resources\UseCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUseCases extends ManageRecords
{
    protected static string $resource = UseCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('admin/home.add_use_case'))];
    }
}
