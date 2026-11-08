<?php

use App\Models\Contact;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->free()->create(['contacts_limit' => 3]);
    $this->owner = User::factory()->create();
});

function contactsCsv(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('contacts.csv', $content);
}

it('requires sign-in', function () {
    $this->postJson('/api/v1/contacts/import')->assertUnauthorized();
});

it('adds each row as a WhatsApp contact, reading Arabic headers and a BOM', function () {
    $csv = "\u{FEFF}الاسم;الجوال;المدينة;الجنس;الوسوم\nريم خالد;+966 50 123 4567;الرياض;أنثى;VIP|عبايات\n";

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/contacts/import', ['file' => contactsCsv($csv), 'consent' => true])
        ->assertOk()
        ->assertJsonPath('data.imported', 1)
        ->assertJsonPath('data.skipped', [])
        ->assertJsonPath('data.usage.used', 1);

    $contact = $this->owner->contacts()->sole();
    expect($contact->only('name', 'phone', 'handle', 'platform', 'city', 'gender', 'tags', 'added_manually'))->toBe([
        'name' => 'ريم خالد',
        'phone' => '+966 50 123 4567',
        'handle' => '+966 50 123 4567',
        'platform' => 'whatsapp',
        'city' => 'الرياض',
        'gender' => 'female',
        'tags' => ['VIP', 'عبايات'],
        'added_manually' => true,
    ])->and($contact->consented_at)->not->toBeNull();
});

it('skips bad rows, known phones and rows past the plan limit with their line numbers', function () {
    Contact::factory()->forUser($this->owner)->create(['phone' => '+966501234567']);
    $csv = "first name,last name,phone,email\n"
        ."Sara,Ali,0501112222,\n"
        ."Omar,,966-50-123-4567,\n"
        .",,0503334444,\n"
        ."Lina,,123,\n"
        ."Huda,,0505556666,huda@example.com\n"
        ."Nour,,0507778888,\n";

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/contacts/import', ['file' => contactsCsv($csv), 'consent' => true])
        ->assertOk()
        ->assertJsonPath('data.imported', 2)
        ->assertJsonPath('data.skipped.*.row', [3, 4, 5, 7])
        ->assertJsonPath('data.skipped.0.reason', __('contacts.import.duplicate'))
        ->assertJsonPath('data.skipped.2.reason', __('contacts.import.phone_short'))
        ->assertJsonPath('data.skipped.3.reason', __('contacts.import.limit'));

    expect($this->owner->contacts()->count())->toBe(3)
        ->and($this->owner->contacts()->whereIn('name', ['Sara Ali', 'Huda'])->count())->toBe(2);
});

it('refuses a file without a name or phone column', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/contacts/import', ['file' => contactsCsv("email\na@example.com\n"), 'consent' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'missing_columns')
        ->assertJsonPath('columns', ['name', 'phone']);

    expect($this->owner->contacts()->count())->toBe(0);
});

it('needs the owner to confirm consent', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/contacts/import', ['file' => contactsCsv("name,phone\nSara,0501112222\n")])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('consent');
});
