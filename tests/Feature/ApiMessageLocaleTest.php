<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->owner = User::factory()->create();
});

it('answers a deleted automation, contact and channel in the language asked for', function (string $lang, string $automation, string $contact, string $channel) {
    $own = Channel::factory()->for($this->owner)->create();
    $rule = Automation::factory()->create(['user_id' => $this->owner->id, 'channel_id' => $own->id]);
    $person = Contact::factory()->forUser($this->owner)->create();

    $this->actingAs($this->owner, 'sanctum');

    $this->deleteJson("/api/v1/automations/{$rule->id}?lang={$lang}")->assertOk()->assertJsonPath('message', $automation);
    $this->deleteJson("/api/v1/contacts/{$person->id}?lang={$lang}")->assertOk()->assertJsonPath('message', $contact);
    $this->deleteJson("/api/v1/channels/{$own->id}?lang={$lang}")->assertOk()->assertJsonPath('message', $channel);
})->with([
    'Arabic' => ['ar', 'نُقلت الأتمتة إلى سلة المحذوفات.', 'حُذفت جهة الاتصال.', 'فُصلت القناة.'],
    'English' => ['en', 'Automation moved to the trash.', 'Contact deleted.', 'Channel disconnected.'],
]);

it('says in Arabic when TikTok refuses a reply, and keeps the code', function () {
    Http::fake(['business-api.tiktok.com/*' => Http::response(['code' => 40002, 'message' => 'Invalid parameter', 'data' => []])]);
    $conversation = Conversation::factory()->for(Channel::factory()->for($this->owner)->create(['platform' => 'tiktok']))->create();

    $this->actingAs($this->owner)
        ->postJson("/api/v1/conversations/{$conversation->id}/messages?lang=ar", ['type' => 'text', 'content' => 'Hi'])
        ->assertStatus(502)
        ->assertJsonPath('code', 'provider_rejected')
        ->assertJsonPath('message', 'لم يوصل TikTok الرسالة. جرّب مرة أخرى بعد قليل.');
});

it('names the help centre in the language asked for', function () {
    $this->actingAs($this->owner, 'sanctum');

    $this->getJson('/api/v1/help?lang=ar')->assertOk()->assertJsonPath('data.title', 'مركز المساعدة');
    $this->getJson('/api/v1/help?lang=en')->assertOk()->assertJsonPath('data.title', 'Help centre');
});
