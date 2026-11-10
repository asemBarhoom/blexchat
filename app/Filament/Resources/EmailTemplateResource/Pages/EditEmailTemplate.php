<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Filament\Support\Translations;
use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label(__('admin/emails.preview'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(__('admin/emails.preview_heading'))
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('admin/common.close'))
                ->modalContent(fn (EmailTemplate $record): View => view('filament.email-template-preview', [
                    'previews' => collect(config('app.supported_locales'))->mapWithKeys(fn (string $locale): array => [$locale => [
                        'language' => Translations::localeName($locale),
                        'subject' => EmailTemplate::compose($record->key, $locale)['subject'],
                        'html' => EmailTemplateResource::previewHtml($record, $locale),
                    ]])->all(),
                ])),
        ];
    }
}
