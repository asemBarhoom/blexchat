<?php

namespace App\Filament\Resources\DemoReplyRuleResource\Pages;

use App\Filament\Resources\DemoReplyRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDemoReplyRules extends ManageRecords
{
    protected static string $resource = DemoReplyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة قاعدة')];
    }
}
