<?php

use App\Filament\Resources\ChannelConnectedScreenResource\Pages\ManageChannelConnectedScreens;
use App\Models\ChannelConnectedScreen;
use App\Models\User;
use Database\Seeders\ChannelConnectedScreenSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a platform’s success copy in both languages', function () {
    Livewire::test(ManageChannelConnectedScreens::class)
        ->callAction(CreateAction::class, [
            'platform' => 'messenger',
            'channel_name' => ['en' => 'Messenger', 'ar' => 'ماسنجر'],
            'title' => ['en' => 'Messenger is in!', 'ar' => 'تم ربط ماسنجر!'],
            'lead' => ['en' => 'Blexchat can now answer.', 'ar' => 'يمكن لـ Blexchat الرد الآن.'],
            'account_kind' => ['en' => 'Facebook Page', 'ar' => 'صفحة فيسبوك'],
            'status_label' => ['en' => 'Connected', 'ar' => 'متصل'],
            'continue_label' => ['en' => 'Continue', 'ar' => 'متابعة'],
            'connect_another_label' => ['en' => 'Connect another channel', 'ar' => 'ربط قناة أخرى'],
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $screen = ChannelConnectedScreen::where('platform', 'messenger')->firstOrFail();
    expect($screen->getTranslation('title', 'ar'))->toBe('تم ربط ماسنجر!');

    Livewire::test(ManageChannelConnectedScreens::class)
        ->callAction(TestAction::make('edit')->table($screen), ['title' => ['en' => 'Done!', 'ar' => 'تم!']])
        ->assertHasNoFormErrors();
    expect($screen->fresh()->getTranslation('title', 'en'))->toBe('Done!');

    Livewire::test(ManageChannelConnectedScreens::class)->callAction(TestAction::make('delete')->table($screen));
    expect(ChannelConnectedScreen::find($screen->id))->toBeNull();
});

it('allows one copy per platform', function () {
    $this->seed(ChannelConnectedScreenSeeder::class);

    Livewire::test(ManageChannelConnectedScreens::class)
        ->callAction(CreateAction::class, [
            'platform' => 'instagram',
            'channel_name' => ['en' => 'Instagram'],
            'title' => ['en' => 'Again'],
            'lead' => ['en' => 'Again'],
            'account_kind' => ['en' => 'Again'],
            'status_label' => ['en' => 'Connected'],
            'continue_label' => ['en' => 'Continue'],
            'connect_another_label' => ['en' => 'Connect another channel'],
        ])
        ->assertHasFormErrors(['platform' => 'unique']);
});

it('lists the seeded copy for all four platforms', function () {
    $this->seed(ChannelConnectedScreenSeeder::class);

    Livewire::test(ManageChannelConnectedScreens::class)
        ->assertCanSeeTableRecords(ChannelConnectedScreen::all())
        ->assertCountTableRecords(4);
});
