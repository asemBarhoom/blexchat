<?php

use App\Models\User;
use Database\Seeders\AutomationHistorySeeder;
use Database\Seeders\AutomationSeeder;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\CommentSeeder;
use Database\Seeders\ContactSeeder;
use Database\Seeders\ConversationSeeder;
use Database\Seeders\OnboardingSeeder;
use Database\Seeders\PostSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, UserSeeder::class, ChannelSeeder::class, PostSeeder::class, AutomationSeeder::class, CommentSeeder::class, ConversationSeeder::class, ContactSeeder::class, AutomationHistorySeeder::class]);
    $this->actingAs(User::where('email', 'nour@nourboutique.com')->sole());
});

it('gives every automation real Runs and the frontend list’s CTR', function () {
    $rows = collect($this->getJson('/api/v1/automations?per_page=50')->assertOk()->json('data'))->keyBy('name');

    expect($rows->map(fn (array $row): array => [$row['runs'], $row['ctr'], $row['automation_folder_id'] !== null])->all())->toEqual([
        'Comment "PRICE" → send the catalogue' => [6, 0.3333, true],
        'Story reply → thank you + link' => [13, 0.3846, true],
        'First DM → welcome and catalogue' => [9, 0.2222, true],
        'Ramadan collection — comment to DM' => [34, 0.4118, true],
        'Eid restock waitlist' => [0, 0, true],
    ]);
});

it('leads the inbox with the frontend’s six, each linked to a complete contact', function () {
    $conversations = $this->getJson('/api/v1/conversations')->assertOk()->json('data');

    expect(array_column(array_column(array_slice($conversations, 0, 6), 'contact'), 'name'))->toBe(['Reem Khalid', 'Sara M.', 'Hala Q.', 'Mona F.', 'Noor A.', 'Lulu'])
        ->and(array_slice($conversations, 0, 6))->each(fn ($conversation) => $conversation->contact->id->not->toBeNull()->contact->handle->not->toBeEmpty()->contact->city->not->toBeNull());

    $contacts = $this->getJson('/api/v1/contacts?per_page=50')->json('data');
    expect($contacts)->each(fn ($contact) => $contact->phone->not->toBeNull()->email->not->toBeNull()->city->not->toBeNull()->gender->not->toBeNull());
    $this->getJson('/api/v1/contacts/usage')->assertJsonPath('data.used', 24)->assertJsonPath('data.over_limit', false);
});

it('opens Reem’s thread with her comment and hands it over', function () {
    $reem = collect($this->getJson('/api/v1/conversations')->json('data'))->firstWhere('contact.name', 'Reem Khalid');

    expect($reem['state'])->toBe('human')
        ->and(collect($this->getJson("/api/v1/conversations/{$reem['id']}/messages")->json('data'))->pluck('type')->sort()->values()->all())
        ->toBe(['comment', 'handover', 'text', 'text_with_button']);
    $this->getJson('/api/v1/dashboard')->assertJsonPath('data.checklist.steps.2.done', true);
});
