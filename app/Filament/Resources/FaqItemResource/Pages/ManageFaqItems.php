<?php

namespace App\Filament\Resources\FaqItemResource\Pages;

use App\Filament\Resources\FaqItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFaqItems extends ManageRecords
{
    protected static string $resource = FaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة سؤال')];
    }
}
