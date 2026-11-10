<?php

namespace App\Filament\Resources\SurveyAnswerResource\Pages;

use App\Filament\Resources\SurveyAnswerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSurveyAnswers extends ManageRecords
{
    protected static string $resource = SurveyAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('admin/survey.create_answer'))];
    }
}
