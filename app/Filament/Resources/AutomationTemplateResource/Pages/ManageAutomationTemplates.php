<?php

namespace App\Filament\Resources\AutomationTemplateResource\Pages;

use App\Filament\Resources\AutomationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAutomationTemplates extends ManageRecords
{
    protected static string $resource = AutomationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة قالب')];
    }
}
