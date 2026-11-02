<?php

namespace App\Filament\Resources\SurveyQuestionResource\Pages;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSurveyQuestion extends EditRecord
{
    protected static string $resource = SurveyQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('حذف'),
        ];
    }
}
