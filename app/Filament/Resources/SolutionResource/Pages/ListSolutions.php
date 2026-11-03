<?php

namespace App\Filament\Resources\SolutionResource\Pages;

use App\Filament\Resources\SolutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSolutions extends ListRecords
{
    protected static string $resource = SolutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة صفحة حل'),
        ];
    }
}
