<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

it('returns the signed-in home screen with real counts', function () {
    $user = User::factory()->create(['name' => 'Nour']);
    $channel = Channel::factory()->for($user)->create(['platform' => 'instagram', 'profile_picture' => 'avatars/sara.webp']);
    $automation = Automation::factory()->for($user)->for($channel)->create(['active' => true]);
    $conversation = Conversation::factory()->for($channel)->create();
    Message::factory()->count(2)->for($conversation)->create([
        'automated' => true,
        'automation_id' => $automation->id,
        'sent_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Nour')
        ->assertJsonPath('data.channels.0.profile_picture', url('/storage/avatars/sara.webp'))
        ->assertJsonPath('data.automations.0.status', 'live')
        ->assertJsonPath('data.automations.0.runs', 2)
        ->assertJsonPath('data.checklist.steps.0.done', true)
        ->assertJsonPath('data.checklist.steps.1.done', true)
        ->assertJsonPath('data.last_seven.has_data', true)
        ->assertJsonPath('data.last_seven.metrics.1.note', '2 replies went out without you.')
        ->assertJsonPath('data.last_seven.metrics.2.locked', true)
        ->assertJsonCount(3, 'data.start_here.templates');
});

it('serves the home screen texts in the requested language', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->for($user)->create();
    $conversation = Conversation::factory()->for($channel)->create();
    Message::factory()->count(3)->for($conversation)->create(['automated' => true, 'sent_at' => now()->subDay()]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/dashboard?lang=ar')
        ->assertJsonPath('data.checklist.title', 'خطواتك الأولى')
        ->assertJsonPath('data.start_here.templates.0.title', 'من التعليق إلى الخاص')
        ->assertJsonPath('data.start_here.templates.0.to', '/onboarding/automation/new?trigger=comment')
        ->assertJsonPath('data.last_seven.metrics.1.label', 'الوقت الموفَّر')
        ->assertJsonPath('data.last_seven.metrics.1.value', '8 د');
});

it('keeps platform avatar URLs as they are', function () {
    $user = User::factory()->create();
    Channel::factory()->for($user)->create(['profile_picture' => 'https://cdn.example.com/a.jpg']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/dashboard')
        ->assertJsonPath('data.channels.0.profile_picture', 'https://cdn.example.com/a.jpg')
        ->assertJsonPath('data.last_seven.has_data', false)
        ->assertJsonPath('data.checklist.steps.1.done', false);
});
