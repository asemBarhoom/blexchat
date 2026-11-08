<?php

namespace App\Filament\Resources\AlignmentItemResource\Pages;

use App\Filament\Resources\AlignmentItemResource;
use App\Filament\Resources\AlignmentItemResource\Widgets\AlignmentProgress;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAlignmentItems extends ManageRecords
{
    protected static string $resource = AlignmentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة بند')];
    }

    protected function getHeaderWidgets(): array
    {
        return [AlignmentProgress::class];
    }
}
