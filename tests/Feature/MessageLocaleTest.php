<?php

use App\Mail\LoginLinkMail;
use App\Mail\ReminderDueMail;
use App\Mail\TeamInvitationMail;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('sends the sign-in link in the language asked for, right to left in Arabic', function (string $lang, string $dir, string $button) {
    Mail::fake();

    $this->postJson("/api/v1/auth/email-link?lang={$lang}", ['email' => 'sara@nourboutique.com', 'mode' => 'signin'])->assertAccepted();

    Mail::sent(LoginLinkMail::class)->first()
        ->assertSeeInHtml("dir=\"{$dir}\"", false)
        ->assertSeeInHtml($button);
})->with([
    'Arabic' => ['ar', 'rtl', 'ادخل إلى Blexchat'],
    'English' => ['en', 'ltr', 'Sign in to Blexchat'],
]);

it('writes the reminder and invitation emails in their language and direction', function (string $locale, string $dir, string $reminder, string $invite) {
    $conversation = Conversation::factory()->create(['contact_name' => 'Reem K.']);
    $invitation = TeamInvitation::factory()->create();

    (new ReminderDueMail($conversation))->locale($locale)
        ->assertSeeInHtml("dir=\"{$dir}\"", false)
        ->assertSeeInHtml($reminder);
    (new TeamInvitationMail($invitation, 'token'))->locale($locale)
        ->assertSeeInHtml("dir=\"{$dir}\"", false)
        ->assertSeeInHtml($invite);
})->with([
    'Arabic' => ['ar', 'rtl', 'افتح صندوق الوارد', 'انضم إلى الفريق'],
    'English' => ['en', 'ltr', 'Open the inbox', 'Join the team'],
]);

it('gives the handover reason in the language asked for', function (string $lang, string $reason) {
    $owner = User::factory()->create();
    $conversation = Conversation::factory()->for(Channel::factory()->for($owner))->create();
    $conversation->handOver('unmatched');

    $handover = collect($this->actingAs($owner, 'sanctum')->getJson("/api/v1/conversations/{$conversation->id}/messages?lang={$lang}")->json('data'))
        ->firstWhere('type', 'handover');

    expect($handover['reason'])->toBe($reason);
})->with([
    'Arabic' => ['ar', 'سأل عن شيء لا تجيب عنه الأتمتة.'],
    'English' => ['en', 'Asked something the automation doesn’t answer.'],
]);

it('writes the development AI handover reason in the owner’s language', function () {
    $client = new FakeClaudeClient;

    expect($client->answer('Nour', 'Abayas', [['text' => 'I want a person']], 'Arabic')['reason'])->toBe('سأل عن شيء لا تغطيه معلومات النشاط (تجربة).')
        ->and($client->answer('Nour', 'Abayas', [['text' => 'I want a person']], 'English')['reason'])->toBe('Asked something the business info doesn’t cover (demo).');
});

it('remembers the language the frontend asks for, and keeps it when none is sent', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner, 'sanctum');

    $this->getJson('/api/v1/help?lang=ar')->assertOk();
    expect($owner->fresh()->locale)->toBe('ar');

    $this->getJson('/api/v1/help', ['Accept-Language' => 'en-US,en;q=0.9'])->assertOk();
    expect($owner->fresh()->locale)->toBe('en');

    $this->getJson('/api/v1/help', ['Accept-Language' => ''])->assertOk();
    expect($owner->fresh()->locale)->toBe('en');
});

it('sends a due reminder in the owner’s own language', function (?string $saved, string $sentIn) {
    Mail::fake();
    $owner = User::factory()->create();
    $owner->forceFill(['locale' => $saved])->save();
    Conversation::factory()->for(Channel::factory()->for($owner))->create(['remind_at' => now()->subMinute()]);

    $this->artisan('conversations:send-reminders')->assertSuccessful();

    Mail::assertSent(ReminderDueMail::class, fn (ReminderDueMail $mail): bool => $mail->locale === $sentIn);
})->with([
    'English owner' => ['en', 'en'],
    'Arabic owner' => ['ar', 'ar'],
    'never said' => [null, 'en'],
]);
