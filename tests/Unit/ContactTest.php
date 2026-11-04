<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a contact on first activity and only refreshes it after', function () {
    $channel = Channel::factory()->create(['platform' => 'instagram']);

    $this->travelTo(now()->subHour());
    $first = Contact::recordActivity($channel, '9001', 'Reem Khalid', 'reem.k', 'https://cdn.example.com/reem.jpg');
    $this->travelBack();
    $again = Contact::recordActivity($channel, '9001');

    expect($again->is($first))->toBeTrue()
        ->and($again->wasRecentlyCreated)->toBeFalse()
        ->and($again->user_id)->toBe($channel->user_id)
        ->and($again->platform)->toBe('instagram')
        ->and($again->name)->toBe('Reem Khalid')
        ->and($again->handle)->toBe('reem.k')
        ->and($again->avatar)->toBe('https://cdn.example.com/reem.jpg')
        ->and($again->first_seen_at->lt($again->last_active_at))->toBeTrue()
        ->and(Contact::count())->toBe(1);
});

it('names a contact by handle, then by platform id, when Instagram shares no name', function () {
    $channel = Channel::factory()->create();

    expect(Contact::recordActivity($channel, '9001', handle: 'sara.m')->name)->toBe('sara.m')
        ->and(Contact::recordActivity($channel, '9002')->name)->toBe('9002');
});

it('searches name, handle, city, tags and channel name, ignoring case', function () {
    $user = User::factory()->create();
    $reem = Contact::factory()->forUser($user)->create(['name' => 'Reem Khalid', 'handle' => 'reem.k', 'city' => 'Jeddah', 'tags' => ['عباية الشتاء']]);
    $added = Contact::factory()->forUser($user)->addedManually()->create(['name' => 'Maha', 'city' => null]);

    $search = fn (string $term): array => Contact::search($term)->pluck('id')->all();

    expect($search('REEM'))->toBe([$reem->id])
        ->and($search('jedd'))->toBe([$reem->id])
        ->and($search('الشتاء'))->toBe([$reem->id])
        ->and($search('whats'))->toBe([$added->id])
        ->and($search('nobody'))->toBe([]);
});

it('filters by exact tag and by source', function () {
    $user = User::factory()->create();
    $automation = Automation::factory()->create(['user_id' => $user->id]);
    $fromAutomation = Contact::factory()->forUser($user)->fromAutomation($automation)->create(['tags' => ['VIP', 'Asked price']]);
    $messaged = Contact::factory()->forUser($user)->create(['tags' => ['VIP customer']]);
    $added = Contact::factory()->forUser($user)->addedManually()->create();

    expect(Contact::withTag('vip')->pluck('id')->all())->toBe([$fromAutomation->id])
        ->and(Contact::fromSource('automation')->pluck('id')->all())->toBe([$fromAutomation->id])
        ->and(Contact::fromSource('messaged')->pluck('id')->all())->toBe([$messaged->id])
        ->and(Contact::fromSource('added')->pluck('id')->all())->toBe([$added->id]);
});

it('sorts the most recently active first, never-active last', function () {
    $user = User::factory()->create();
    $never = Contact::factory()->forUser($user)->create(['last_active_at' => null]);
    $old = Contact::factory()->forUser($user)->create(['last_active_at' => now()->subDay()]);
    $recent = Contact::factory()->forUser($user)->create(['last_active_at' => now()]);

    expect(Contact::sortedBy('recent')->pluck('id')->all())->toBe([$recent->id, $old->id, $never->id]);
});

it('counts contacts against the plan in force, Free once a trial ends', function () {
    Plan::factory()->free()->create();
    $pro = Plan::factory()->create(['contacts_limit' => 2500]);
    $user = User::factory()->create();
    Contact::factory()->count(3)->forUser($user)->create();

    expect($user->contactsUsage())->toBe(['used' => 3, 'limit' => 25, 'plan' => 'free', 'over_limit' => false]);

    Subscription::factory()->trialing()->create(['user_id' => $user->id, 'plan_id' => $pro->id]);
    expect($user->refresh()->contactsUsage()['limit'])->toBe(2500);

    $user->subscription->update(['trial_ends_at' => now()->subDay()]);
    expect($user->refresh()->contactsUsage()['limit'])->toBe(25);
});

it('stops adding by hand at the limit but reports going over it', function () {
    Plan::factory()->free()->create(['contacts_limit' => 2]);
    $user = User::factory()->create();
    Contact::factory()->forUser($user)->create();

    expect($user->canAddContact())->toBeTrue();

    Contact::factory()->count(2)->forUser($user)->create();

    expect($user->canAddContact())->toBeFalse()
        ->and($user->contactsUsage()['over_limit'])->toBeTrue();
});
