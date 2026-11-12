<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

class ManageHelpCentre extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $slug = 'help-centre';

    public ?array $data = [];

    protected string $view = 'filament.pages.manage-help-centre';

    public static function getNavigationLabel(): string
    {
        return __('admin/help.nav');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_content');
    }

    public function getTitle(): string
    {
        return __('admin/help.title');
    }

    public function mount(): void
    {
        $this->form->fill(['email' => Setting::get(Setting::HELP_EMAIL) ?? config('mail.from.address'), 'response_time_hours' => Setting::get(Setting::HELP_RESPONSE_TIME)]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('email')->label(__('admin/help.email'))->email()->required(),
            TextInput::make('response_time_hours')->label(__('admin/help.response_time'))->numeric()->integer()->minValue(1)->required(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')->footer([
                Actions::make([Action::make('save')->label(__('admin/common.save'))->submit('save')])->key('form-actions'),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        Setting::put(Setting::HELP_EMAIL, $state['email']);
        Setting::put(Setting::HELP_RESPONSE_TIME, (int) $state['response_time_hours']);
        Notification::make()->title(__('admin/help.saved'))->success()->send();
    }
}
