<?php

namespace App\Filament\Resources\CreatorResource\Pages;

use App\Filament\Resources\CreatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCreators extends ManageRecords
{
    protected static string $resource = CreatorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة صانع محتوى')];
    }
}
