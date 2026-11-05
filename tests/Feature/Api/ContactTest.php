<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->owner = User::factory()->create();
});

it('requires sign-in', function () {
    $this->getJson('/api/v1/contacts')->assertUnauthorized();
    $this->getJson('/api/v1/contacts/usage')->assertUnauthorized();
});

describe('GET /api/v1/contacts', function () {
    it('lists only the owner’s contacts, most recently active first, with the meter', function () {
        $automation = Automation::factory()->create(['user_id' => $this->owner->id, 'name' => 'Auto-DM links from comments']);
        $older = Contact::factory()->forUser($this->owner)->create(['last_active_at' => now()->subHour()]);
        $recent = Contact::factory()->forUser($this->owner)->fromAutomation($automation)->create(['name' => 'Reem Khalid', 'handle' => 'reem.k', 'tags' => ['Winter abaya'], 'last_active_at' => now()]);
        Contact::factory()->create();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$recent->id, $older->id])
            ->assertJsonPath('data.0.name', 'Reem Khalid')
            ->assertJsonPath('data.0.handle', 'reem.k')
            ->assertJsonPath('data.0.channel', 'instagram')
            ->assertJsonPath('data.0.tags', ['Winter abaya'])
            ->assertJsonPath('data.0.source', 'Auto-DM links from comments')
            ->assertJsonPath('data.0.added', false)
            ->assertJsonPath('data.1.source', null)
            ->assertJsonMissingPath('data.0.conversation')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.usage', ['used' => 2, 'limit' => 25, 'plan' => 'free', 'over_limit' => false]);
    });

    it('searches and filters, keeping the unfiltered count in the meter', function () {
        Contact::factory()->forUser($this->owner)->create(['name' => 'Reem Khalid', 'city' => 'Jeddah', 'tags' => ['Asked price']]);
        Contact::factory()->forUser($this->owner)->create(['name' => 'Sara M.', 'city' => 'Riyadh', 'tags' => ['Asked price']]);
        $added = Contact::factory()->forUser($this->owner)->addedManually()->create(['name' => 'Maha']);

        $this->actingAs($this->owner, 'sanctum');

        $this->getJson('/api/v1/contacts?search=jeddah')
            ->assertJsonPath('data.*.name', ['Reem Khalid'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.usage.used', 3);
        $this->getJson('/api/v1/contacts?tag=asked+price&sort=name')->assertJsonPath('data.*.name', ['Reem Khalid', 'Sara M.']);
        $this->getJson('/api/v1/contacts?channel=whatsapp')->assertJsonPath('data.*.id', [$added->id]);
        $this->getJson('/api/v1/contacts?source=added')->assertJsonPath('data.*.id', [$added->id]);
        $this->getJson('/api/v1/contacts?search=nobody')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/contacts?per_page=2&page=2')->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/contacts?sort=oldest&channel=fax')->assertUnprocessable()->assertJsonValidationErrors(['sort', 'channel']);
    });

    it('is empty for a new account', function () {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.usage.used', 0);
    });
});

it('reports the sidebar meter against the plan in force', function () {
    Contact::factory()->count(2)->forUser($this->owner)->create();

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/contacts/usage')
        ->assertOk()
        ->assertExactJson(['data' => ['used' => 2, 'limit' => 25, 'plan' => 'free', 'over_limit' => false]]);
});

describe('POST /api/v1/contacts', function () {
    it('adds someone by hand as a WhatsApp contact with their consent', function () {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v1/contacts', [
                'first_name' => ' Maha ', 'last_name' => 'Saad', 'dial_code' => '+966', 'phone' => '55 123 4567',
                'email' => 'maha@example.com', 'gender' => 'female', 'consent' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Maha Saad')
            ->assertJsonPath('data.handle', '+966 55 123 4567')
            ->assertJsonPath('data.phone', '+966 55 123 4567')
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.added', true)
            ->assertJsonPath('data.source', null)
            ->assertJsonPath('data.last_active_at', null);

        expect($this->owner->contacts()->sole()->consented_at)->not->toBeNull();
    });

    it('needs a name, a long enough number and consent', function () {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v1/contacts', ['dial_code' => '966', 'phone' => '12 34', 'email' => 'nope', 'consent' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'dial_code', 'phone', 'email', 'consent']);
    });

    it('refuses once the plan’s limit is reached', function () {
        Plan::where('slug', 'free')->update(['contacts_limit' => 1]);
        Contact::factory()->forUser($this->owner)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v1/contacts', ['first_name' => 'Maha', 'dial_code' => '+966', 'phone' => '551234567', 'consent' => true])
            ->assertForbidden()
            ->assertJsonPath('usage.used', 1)
            ->assertJsonPath('usage.limit', 1);

        expect($this->owner->contacts()->count())->toBe(1);
    });
});

describe('GET /api/v1/contacts/{id}', function () {
    it('shows a contact with their conversation for "Open in Inbox"', function () {
        $contact = Contact::factory()->forUser($this->owner)->create();
        $conversation = Conversation::factory()->create(['channel_id' => $contact->channel_id, 'contact_id' => $contact->id, 'status' => 'open', 'state' => 'human']);
        Message::factory()->count(3)->create(['conversation_id' => $conversation->id]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.conversation', ['id' => $conversation->id, 'status' => 'open', 'state' => 'human', 'messages_count' => 3]);
    });

    it('shows no conversation for someone added by hand', function () {
        $contact = Contact::factory()->forUser($this->owner)->addedManually()->create();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation', null);
    });

    it('hides other owners’ contacts', function () {
        $theirs = Contact::factory()->create();

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/contacts/{$theirs->id}")->assertNotFound();
        $this->patchJson("/api/v1/contacts/{$theirs->id}", ['tags' => []])->assertNotFound();
        $this->deleteJson("/api/v1/contacts/{$theirs->id}")->assertNotFound();

        expect($theirs->fresh())->not->toBeNull();
    });
});

it('saves the edited tags, trimmed and without repeats', function () {
    $contact = Contact::factory()->forUser($this->owner)->create(['tags' => ['Asked price']]);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/contacts/{$contact->id}", ['tags' => ['Asked price', ' عباية الشتاء ', 'VIP']])
        ->assertOk()
        ->assertJsonPath('data.tags', ['Asked price', 'عباية الشتاء', 'VIP']);

    $this->patchJson("/api/v1/contacts/{$contact->id}", ['tags' => ['VIP', 'vip']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.1']);

    expect($contact->fresh()->tags)->toBe(['Asked price', 'عباية الشتاء', 'VIP']);
});

it('deletes a contact and keeps their conversation', function () {
    $contact = Contact::factory()->forUser($this->owner)->create();
    $conversation = Conversation::factory()->create(['channel_id' => Channel::factory()->create(['user_id' => $this->owner->id])->id, 'contact_id' => $contact->id]);

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/contacts/{$contact->id}")
        ->assertOk();

    expect($contact->fresh())->toBeNull()
        ->and($conversation->fresh()->contact_id)->toBeNull();
});
