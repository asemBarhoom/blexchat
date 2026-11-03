<?php

use App\Filament\Resources\ChannelOptionResource\Pages\ManageChannelOptions;
use App\Models\ChannelOption;
use App\Models\User;
use Database\Seeders\ChannelOptionSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a channel in both languages', function () {
    ChannelOption::factory()->create(['platform' => 'instagram']);

    Livewire::test(ManageChannelOptions::class)
        ->callAction(CreateAction::class, [
            'platform' => 'whatsapp',
            'name' => ['en' => 'WhatsApp', 'ar' => 'واتساب'],
            'line' => ['en' => 'Chats with your customers', 'ar' => 'محادثاتك مع عملائك'],
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $channel = ChannelOption::where('platform', 'whatsapp')->firstOrFail();
    expect($channel->sort)->toBe(2);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/onboarding/channels?lang=ar')
        ->assertJsonPath('data.1.name', 'واتساب');

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(ManageChannelOptions::class)
        ->callAction(TestAction::make('edit')->table($channel), ['line' => ['en' => 'Your WhatsApp Business chats', 'ar' => 'محادثات واتساب للأعمال']])
        ->assertHasNoFormErrors();
    expect($channel->fresh()->getTranslation('line', 'en'))->toBe('Your WhatsApp Business chats');

    Livewire::test(ManageChannelOptions::class)->callAction(TestAction::make('delete')->table($channel));
    expect(ChannelOption::find($channel->id))->toBeNull();
});

it('refuses a second row for the same platform', function () {
    ChannelOption::factory()->create(['platform' => 'tiktok']);

    Livewire::test(ManageChannelOptions::class)
        ->callAction(CreateAction::class, ['platform' => 'tiktok', 'name' => ['en' => 'TikTok'], 'line' => ['en' => 'Videos']])
        ->assertHasFormErrors(['platform' => 'unique']);
});

it('reorders channels and switches one off from the table', function () {
    $this->seed(ChannelOptionSeeder::class);
    $messenger = ChannelOption::where('platform', 'messenger')->firstOrFail();
    $ids = ChannelOption::orderBy('sort')->pluck('id')->all();

    Livewire::test(ManageChannelOptions::class)
        ->call('reorderTable', array_map('strval', [$messenger->id, ...array_diff($ids, [$messenger->id])]))
        ->call('updateTableColumnState', 'active', (string) ChannelOption::where('platform', 'tiktok')->value('id'), false);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/onboarding/channels')
        ->assertJsonPath('data.*.id', ['messenger', 'instagram', 'whatsapp']);
});
