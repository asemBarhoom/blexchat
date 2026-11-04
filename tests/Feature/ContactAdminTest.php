<?php

use App\Filament\Resources\ContactResource\Pages\ManageContacts;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a contact', function () {
    $owner = User::factory()->create();
    $channel = Channel::factory()->create(['user_id' => $owner->id, 'platform' => 'instagram']);

    Livewire::test(ManageContacts::class)
        ->callAction(CreateAction::class, [
            'user_id' => $owner->id,
            'channel_id' => $channel->id,
            'platform' => 'instagram',
            'platform_id' => '9001',
            'name' => 'Reem Khalid',
            'handle' => 'reem.k',
            'city' => 'Jeddah',
            'gender' => 'female',
            'tags' => ['عباية الشتاء', 'VIP'],
            'first_seen_at' => now()->toDateTimeString(),
        ])
        ->assertHasNoFormErrors();

    $contact = Contact::sole();
    expect($contact->user_id)->toBe($owner->id)
        ->and($contact->channel_id)->toBe($channel->id)
        ->and($contact->tags)->toBe(['عباية الشتاء', 'VIP'])
        ->and($contact->subscribed)->toBeTrue();

    Livewire::test(ManageContacts::class)
        ->assertCanSeeTableRecords([$contact])
        ->callAction(TestAction::make('edit')->table($contact), ['city' => 'Riyadh', 'subscribed' => false])
        ->assertHasNoFormErrors();
    expect($contact->fresh())->city->toBe('Riyadh')->subscribed->toBeFalse();

    Livewire::test(ManageContacts::class)->callAction(TestAction::make('delete')->table($contact));
    expect(Contact::find($contact->id))->toBeNull();
});

it('requires an owner, a name and a platform', function () {
    Livewire::test(ManageContacts::class)
        ->callAction(CreateAction::class, ['platform' => null, 'name' => ''])
        ->assertHasFormErrors(['user_id' => 'required', 'name' => 'required', 'platform' => 'required']);
});
