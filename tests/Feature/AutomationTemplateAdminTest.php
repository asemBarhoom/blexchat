<?php

use App\Filament\Resources\AutomationTemplateResource\Pages\ManageAutomationTemplates;
use App\Models\AutomationTemplate;
use App\Models\User;
use Database\Seeders\AutomationTemplateSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AutomationTemplateSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a template in both languages', function () {
    Livewire::test(ManageAutomationTemplates::class)
        ->callAction(CreateAction::class, [
            'key' => 't10',
            'name' => ['en' => 'Comment → send the size guide', 'ar' => 'تعليق ← دليل المقاسات'],
            'trigger' => 'comment',
            'build' => 'quick',
            'goal' => 'support',
            'match' => 'words',
            'keywords' => ['en' => ['size'], 'ar' => ['مقاس']],
            'public_reply' => ['en' => 'Check your DMs', 'ar' => 'شوفي الخاص'],
            'dm_text' => ['en' => 'Here is the size guide:', 'ar' => 'هذا دليل المقاسات:'],
            'dm_button' => ['en' => 'Size guide', 'ar' => 'دليل المقاسات'],
            'link_url' => 'https://nourboutique.com/sizes',
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $template = AutomationTemplate::where('key', 't10')->firstOrFail();
    expect($template->getTranslations('keywords'))->toBe(['en' => ['size'], 'ar' => ['مقاس']]);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/automation-templates?lang=ar')
        ->assertJsonPath('data.9.name', 'تعليق ← دليل المقاسات')
        ->assertJsonPath('data.9.draft.words', ['مقاس']);

    Livewire::test(ManageAutomationTemplates::class)
        ->callAction(TestAction::make('edit')->table($template), ['locked' => true])
        ->assertHasNoFormErrors();
    expect($template->fresh()->locked)->toBeTrue();

    Livewire::test(ManageAutomationTemplates::class)->callAction(TestAction::make('delete')->table($template));
    expect(AutomationTemplate::find($template->id))->toBeNull();
});

it('asks a quick template for its message, but not a flow one', function () {
    Livewire::test(ManageAutomationTemplates::class)
        ->callAction(CreateAction::class, ['key' => 'q', 'name' => ['en' => 'Quick'], 'trigger' => 'dm', 'build' => 'quick', 'goal' => 'sell'])
        ->assertHasFormErrors(['match' => 'required', 'dm_text.en' => 'required']);

    Livewire::test(ManageAutomationTemplates::class)
        ->callAction(CreateAction::class, ['key' => 'f', 'name' => ['en' => 'Flow'], 'trigger' => 'dm', 'build' => 'flow', 'goal' => 'sell'])
        ->assertHasNoFormErrors();
});

it('keeps one starter per trigger, and starters from being deleted', function () {
    $starter = AutomationTemplate::where('key', 'starter-dm')->firstOrFail();

    Livewire::test(ManageAutomationTemplates::class)
        ->assertActionHidden(TestAction::make('delete')->table($starter))
        ->callAction(CreateAction::class, [
            'key' => 'starter-dm-2', 'is_starter' => true, 'name' => ['en' => 'Another'], 'trigger' => 'dm', 'build' => 'quick',
            'match' => 'any', 'dm_text' => ['en' => 'Hi'],
        ])
        ->assertHasFormErrors(['is_starter']);
});
