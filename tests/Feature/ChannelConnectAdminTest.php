<?php

use App\Filament\Resources\ChannelAccessRequestResource\Pages\ManageChannelAccessRequests;
use App\Filament\Resources\ChannelConnectScreenResource\Pages\CreateChannelConnectScreen;
use App\Filament\Resources\ChannelConnectScreenResource\Pages\EditChannelConnectScreen;
use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use App\Models\ChannelConnectScreen;
use App\Models\User;
use Database\Seeders\ChannelConnectScreenSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChannelConnectScreenSeeder::class);
    $this->actingAs($this->admin = User::factory()->admin()->create());
});

it('renders the edit page of a seeded connect screen', function () {
    $this->get('/admin/channel-connect-screens/'.ChannelConnectScreen::where('platform', 'instagram')->firstOrFail()->id.'/edit')
        ->assertOk()
        ->assertSee('Connect via Meta');
});

it('edits a connect screen in both languages and serves it to owners', function () {
    $screen = ChannelConnectScreen::where('platform', 'messenger')->firstOrFail();

    Livewire::test(EditChannelConnectScreen::class, ['record' => $screen->getRouteKey()])
        ->fillForm([
            'cta' => ['en' => 'Link your Page', 'ar' => 'اربط صفحتك'],
            'steps' => ['en' => ['One', 'Two', 'Three'], 'ar' => ['واحد', 'اثنان', 'ثلاثة']],
            'options' => [
                'en' => [['title' => 'Help', 'note' => 'Read this', 'url' => 'https://help.blexchat.test/pages']],
                'ar' => [['title' => 'مساعدة', 'note' => null, 'url' => null]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/onboarding/channels/messenger/connect')
        ->assertJsonPath('data.cta', 'Link your Page')
        ->assertJsonPath('data.steps', ['One', 'Two', 'Three'])
        ->assertJsonPath('data.options', [['title' => 'Help', 'note' => 'Read this', 'url' => 'https://help.blexchat.test/pages']]);
    $this->getJson('/api/v1/onboarding/channels/messenger/connect?lang=ar')->assertJsonPath('data.options.0.title', 'مساعدة');
});

it('creates a connect screen and refuses a second one for the same channel', function () {
    ChannelConnectScreen::where('platform', 'tiktok')->delete();

    $form = [
        'platform' => 'tiktok',
        'method' => 'tiktok',
        'name' => ['en' => 'TikTok', 'ar' => 'تيك توك'],
        'cta' => ['en' => 'Continue with TikTok'],
        'lead' => ['en' => 'Blexchat connects through TikTok.'],
        'steps' => ['en' => ['Log in', 'Choose', 'Authorize']],
        'reconnect_cta' => ['en' => 'Reconnect with TikTok'],
        'waiting_in' => ['en' => 'the TikTok window'],
        'ineligible_title' => ['en' => 'This is a personal TikTok account.'],
        'ineligible_text' => ['en' => 'Switch to Business.'],
        'active' => true,
    ];

    Livewire::test(CreateChannelConnectScreen::class)->fillForm($form)->call('create')->assertHasNoFormErrors();
    expect(ChannelConnectScreen::where('platform', 'tiktok')->firstOrFail()->getTranslation('name', 'ar'))->toBe('تيك توك');

    Livewire::test(CreateChannelConnectScreen::class)->fillForm($form)->call('create')->assertHasFormErrors(['platform' => 'unique']);
});

it('creates, decides and deletes access requests', function () {
    $owner = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create(['platform' => 'instagram', 'username' => 'nourboutique']);
    $asker = User::factory()->create();

    Livewire::test(ManageChannelAccessRequests::class)
        ->callAction(CreateAction::class, ['user_id' => $asker->id, 'channel_id' => $channel->id, 'platform' => 'instagram', 'account_id' => $channel->account_id, 'account_handle' => 'nourboutique', 'status' => 'pending'])
        ->assertHasNoFormErrors();

    $request = ChannelAccessRequest::where('user_id', $asker->id)->firstOrFail();
    expect($request->decided_at)->toBeNull();

    Livewire::test(ManageChannelAccessRequests::class)
        ->assertCanSeeTableRecords([$request])
        ->callAction(TestAction::make('edit')->table($request), ['status' => 'approved', 'note' => 'Owner agreed by phone'])
        ->assertHasNoFormErrors();
    expect($request->fresh())->status->toBe('approved')->decided_at->not->toBeNull();

    Livewire::test(ManageChannelAccessRequests::class)->callAction(TestAction::make('delete')->table($request));
    $this->assertModelMissing($request);
});
