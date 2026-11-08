<?php

use App\Mail\ReminderDueMail;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

describe('GET /api/v1/conversations', function () {
    it('returns conversations for authenticated user channels', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        Conversation::factory()->count(3)->create(['channel_id' => $channel->id]);
        Conversation::factory()->create(); // different channel

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/conversations')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });
});

describe('GET /api/v1/conversations/{id}/messages', function () {
    it('returns messages for a conversation', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);
        Message::factory()->count(5)->create(['conversation_id' => $conversation->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
    });

    it('puts the later of two messages sent in the same second first', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create();
        $sentAt = now()->startOfSecond();
        $question = Message::factory()->for($conversation)->create(['sent_at' => $sentAt]);
        $autoReply = Message::factory()->for($conversation)->create(['sent_at' => $sentAt]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertJsonPath('data.0.id', $autoReply->id)
            ->assertJsonPath('data.1.id', $question->id);
    });

    it('returns 403 for conversation from another user channel', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(); // different channel

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(403);
    });
});

describe('POST /api/v1/conversations/{id}/messages', function () {
    it('sends a text message', function () {
        $user = User::factory()->create();
        // Instagram sends through its API; that path is covered in InstagramIntegrationTest.
        $channel = Channel::factory()->create(['user_id' => $user->id, 'platform' => 'whatsapp']);
        $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'type' => 'text',
                'content' => 'Hello, your order is on the way!',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'sent_at']]);
    });

    it('validates button fields for text_with_button type', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'type' => 'text_with_button',
                'content' => 'Track here:',
                // missing button_label and button_url
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['button_label', 'button_url']);
    });
});

describe('PATCH /api/v1/conversations/{id}', function () {
    it('marks a conversation done, reopens it, and marks it read', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create(['status' => 'open', 'unread_count' => 3]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.unread', 3);

        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['read' => true])
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.unread', 0);

        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'open'])->assertJsonPath('data.status', 'open');
        $this->getJson('/api/v1/conversations?status=resolved')->assertJsonCount(0, 'data');
    });

    it('lists only unread conversations when asked', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->for($user)->create();
        $unread = Conversation::factory()->for($channel)->create(['unread_count' => 2]);
        Conversation::factory()->for($channel)->create(['unread_count' => 0]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/conversations?unread=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);
    });

    it('keeps changes to the owner and to known states', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'resolved'])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['status' => 'snoozed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson("/api/v1/conversations/{$conversation->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'read']);
    });
});

describe('/api/v1/inbox/settings', function () {
    it('accepts new conversations until the owner switches it off', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/inbox/settings')->assertOk()->assertExactJson(['data' => ['accepting_new' => true]]);

        $this->patchJson('/api/v1/inbox/settings', ['accepting_new' => false])->assertOk()->assertJsonPath('data.accepting_new', false);
        $this->getJson('/api/v1/inbox/settings')->assertJsonPath('data.accepting_new', false);

        $this->patchJson('/api/v1/inbox/settings', [])->assertUnprocessable()->assertJsonValidationErrors('accepting_new');
    });
});

describe('the contact panel', function () {
    it('gives each conversation its contact, and the automation that brought them in', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->for($user)->create();
        $automation = Automation::factory()->for($user)->for($channel)->create(['name' => 'Comment to DM']);
        $contact = Contact::factory()->for($user)->create(['channel_id' => $channel->id, 'tags' => ['Asked price'], 'city' => 'Cairo', 'source_automation_id' => $automation->id]);
        $conversation = Conversation::factory()->for($channel)->create(['contact_id' => $contact->id]);
        $unlinked = Conversation::factory()->for($channel)->create(['contact_name' => 'old.thread', 'contact_platform_id' => 'ig_old_thread']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/conversations')
            ->assertJsonFragment(['id' => $contact->id, 'name' => $contact->name, 'tags' => ['Asked price'], 'city' => 'Cairo', 'source' => 'Comment to DM', 'source_automation_id' => $automation->id])
            ->assertJsonFragment(['id' => $unlinked->contact_id, 'name' => 'old.thread', 'handle' => 'old.thread', 'tags' => [], 'source' => null]);

        expect($unlinked->contact)->platform_id->toBe('ig_old_thread')->user_id->toBe($user->id);

        $automation->moveToTrash();
        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['read' => true])->assertJsonPath('data.contact.source', 'Comment to DM');
    });
});

describe('the inbox folders', function () {
    it('stars and labels a conversation, and files it in Favorites and Labels', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->for($user)->create();
        $vip = Conversation::factory()->for($channel)->create();
        $plain = Conversation::factory()->for($channel)->create();

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/conversations/{$vip->id}", ['starred' => true, 'labels' => [' VIP ', 'Wholesale']])
            ->assertOk()
            ->assertJsonPath('data.starred', true)
            ->assertJsonPath('data.labels', ['VIP', 'Wholesale']);

        $this->getJson('/api/v1/conversations?folder=favorites')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $vip->id);
        $this->getJson('/api/v1/conversations?folder=labels')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/conversations?label=Wholesale')->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/conversations/{$vip->id}", ['labels' => []])->assertJsonPath('data.labels', []);
        $this->patchJson("/api/v1/conversations/{$vip->id}", ['starred' => false])->assertJsonPath('data.starred', false);
        $this->getJson('/api/v1/conversations?folder=labels')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/conversations?folder=favorites')->assertJsonCount(0, 'data');
        expect($plain->refresh()->starred)->toBeFalse();
    });

    it('refuses a repeated label, a long one, or too many', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create();

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/conversations/{$conversation->id}", ['labels' => ['VIP', 'vip']])->assertJsonValidationErrors('labels.1');
        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['labels' => [str_repeat('a', 31)]])->assertJsonValidationErrors('labels.0');
        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['labels' => range(1, 11)])->assertJsonValidationErrors('labels');
    });

    it('lists the oldest conversations first on request', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->for($user)->create();
        $old = Conversation::factory()->for($channel)->create(['last_message_at' => now()->subDays(3)]);
        $new = Conversation::factory()->for($channel)->create(['last_message_at' => now()]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/conversations')->assertJsonPath('data.0.id', $new->id);
        $this->getJson('/api/v1/conversations?sort=oldest')->assertJsonPath('data.0.id', $old->id);
    });
});

describe('the Reminders folder and the folder count', function () {
    it('sets a reminder on a paid plan, lists Reminders soonest first, and clears it', function () {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->for(Plan::factory()->create())->create(['status' => 'active']);
        $channel = Channel::factory()->for($user)->create();
        [$later, $sooner] = Conversation::factory()->for($channel)->count(2)->create();
        Conversation::factory()->for($channel)->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/conversations/{$later->id}", ['remind_at' => now()->addDays(2)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.remind_at', fn (?string $at): bool => $at !== null);
        $this->patchJson("/api/v1/conversations/{$sooner->id}", ['remind_at' => now()->addHour()->toIso8601String()])->assertOk();

        $this->getJson('/api/v1/conversations?folder=reminders')
            ->assertJsonPath('data.*.id', [$sooner->id, $later->id])
            ->assertJsonPath('meta.total', 2);

        $this->patchJson("/api/v1/conversations/{$later->id}", ['remind_at' => null])->assertOk()->assertJsonPath('data.remind_at', null);
        $this->getJson('/api/v1/conversations?folder=reminders')->assertJsonPath('meta.total', 1);
    });

    it('emails the owner once when a reminder comes, marks it due, and emails again for a new one', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'nour@nourboutique.com']);
        Subscription::factory()->for($user)->for(Plan::factory()->create())->create(['status' => 'active']);
        $channel = Channel::factory()->for($user)->create();
        $due = Conversation::factory()->for($channel)->create(['contact_name' => 'Reem K.', 'remind_at' => now()->addHour()]);
        $later = Conversation::factory()->for($channel)->create(['remind_at' => now()->addDays(2)]);

        $this->travel(61)->minutes();
        $this->artisan('conversations:send-reminders')->assertSuccessful();
        $this->artisan('conversations:send-reminders')->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(ReminderDueMail::class, fn (ReminderDueMail $mail): bool => $mail->hasTo('nour@nourboutique.com') && $mail->conversation->is($due));
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/conversations?folder=reminders')
            ->assertJsonPath('data.0.remind_due', true)
            ->assertJsonPath('data.1.remind_due', false);

        $this->patchJson("/api/v1/conversations/{$due->id}", ['remind_at' => now()->addMinutes(5)->toIso8601String()])->assertOk()->assertJsonPath('data.remind_due', false);
        $this->travel(6)->minutes();
        $this->artisan('conversations:send-reminders')->assertSuccessful();

        Mail::assertSentCount(2);
        expect($later->fresh()->reminded_at)->toBeNull();
    });

    it('keeps reminders to paid plans and to the future', function () {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for(Channel::factory()->for($user))->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/conversations/{$conversation->id}", ['remind_at' => now()->addDay()->toIso8601String()])
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_required');

        $this->patchJson("/api/v1/conversations/{$conversation->id}", ['remind_at' => now()->subDay()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remind_at');
        expect($conversation->fresh()->remind_at)->toBeNull();
    });

    it('counts every conversation the filters match, beyond the page', function () {
        $user = User::factory()->create();
        $channel = Channel::factory()->for($user)->create();
        Conversation::factory()->for($channel)->count(3)->create(['unread_count' => 1]);
        Conversation::factory()->for($channel)->count(2)->create(['unread_count' => 0]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/conversations?limit=2')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);
        $this->getJson('/api/v1/conversations?unread=1')->assertJsonPath('meta.total', 3);
    });
});
