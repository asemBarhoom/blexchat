<?php

namespace App\Filament\Resources\PricingFaqItemResource\Pages;

use App\Filament\Resources\PricingFaqItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePricingFaqItems extends ManageRecords
{
    protected static string $resource = PricingFaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('admin/survey.create_question'))];
    }
}
