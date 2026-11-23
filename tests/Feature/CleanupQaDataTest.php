<?php

use App\Models\Channel;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('inventories the exact QA accounts without changing data', function () {
    $qa = User::factory()->create(['email' => 'nour@nourboutique.com', 'name' => 'Nour', 'business_name' => 'Nour Boutique', 'is_admin' => false]);
    Channel::factory()->create(['user_id' => $qa->id, 'platform' => 'instagram', 'account_id' => '17841400000000001']);

    $this->artisan('qa:cleanup')->expectsOutputToContain('Dry run only. No data was changed.')->assertSuccessful();
    $this->assertDatabaseHas('users', ['id' => $qa->id]);
});

it('supports the legacy cleanup command name with the same dry-run guard', function () {
    $qa = User::factory()->create(['email' => 'nour@nourboutique.com', 'name' => 'Nour', 'business_name' => 'Nour Boutique', 'is_admin' => false]);

    $this->artisan('app:clean-qa-data')
        ->expectsOutputToContain('Dry run only. No data was changed.')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['id' => $qa->id]);
});

it('refuses a matching email when operational data is not seeded QA data', function () {
    $qa = User::factory()->create(['email' => 'nour@nourboutique.com', 'name' => 'Nour', 'business_name' => 'Nour Boutique', 'is_admin' => false]);
    Channel::factory()->create(['user_id' => $qa->id, 'platform' => 'instagram', 'account_id' => 'a-real-account']);

    $this->artisan('qa:cleanup', ['--execute' => true, '--force' => true, '--backup-reference' => 'snapshot-1'])
        ->expectsOutputToContain('has a channel outside the seeded QA identifiers')->assertFailed();
    $this->assertDatabaseHas('users', ['id' => $qa->id]);
});

it('requires an explicit backup and deletes only exact QA candidates', function () {
    $qa = User::factory()->create(['email' => 'nour@nourboutique.com', 'name' => 'Nour', 'business_name' => 'Nour Boutique', 'is_admin' => false]);
    $channel = Channel::factory()->create(['user_id' => $qa->id, 'platform' => 'instagram', 'account_id' => '17841400000000001']);
    $contact = Contact::factory()->create(['user_id' => $qa->id, 'channel_id' => $channel->id]);
    $real = User::factory()->create();

    $this->artisan('qa:cleanup', ['--execute' => true, '--force' => true])->assertFailed();
    $this->assertDatabaseHas('users', ['id' => $qa->id]);
    $this->artisan('qa:cleanup', ['--execute' => true, '--force' => true, '--backup-reference' => 'snapshot-1'])
        ->expectsOutputToContain('Deleted 1 QA account(s). Backup: snapshot-1')->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $qa->id]);
    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    $this->assertDatabaseHas('users', ['id' => $real->id]);
});
