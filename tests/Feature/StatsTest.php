<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create(['platform' => 'instagram']);
    $this->automation = Automation::factory()->for($this->owner)->for($this->channel)->create(['extras' => ['ask_email' => true]]);
    $this->conversation = Conversation::factory()->for($this->channel)->create();
});

/**
 * An automatic DM from this automation; with a button when $clicked is not null.
 */
function automatedDm(?bool $clicked = null, string $sentAt = '-1 day'): Message
{
    return Message::factory()->for(test()->conversation)->create([
        'direction' => 'outbound',
        'automated' => true,
        'automation_id' => test()->automation->id,
        'sent_at' => now()->modify($sentAt),
        'button_label' => $clicked === null ? null : 'Shop',
        'button_url' => $clicked === null ? null : 'https://nourboutique.com/abaya',
        'link_token' => $clicked === null ? null : Str::random(32),
        'first_clicked_at' => $clicked ? now() : null,
    ]);
}

function commentOn(Post $post, string $postedAt = '-1 day', ?Automation $answeredBy = null): Comment
{
    return Comment::create([
        'post_id' => $post->id,
        'channel_id' => $post->channel_id,
        'platform_comment_id' => Str::random(12),
        'author_name' => 'reem.k',
        'author_platform_id' => Str::random(8),
        'text' => 'price?',
        'automated_reply' => $answeredBy !== null,
        'automation_id' => $answeredBy?->id,
        'posted_at' => now()->modify($postedAt),
    ]);
}

describe('GET /api/v1/automations/{id}/stats', function () {
    it('says "no data yet" until the automation has answered someone', function () {
        $this->actingAs($this->owner)->getJson("/api/v1/automations/{$this->automation->id}/stats")
            ->assertOk()
            ->assertExactJson(['data' => ['sends' => null, 'public_replies' => null, 'clicks' => null, 'ctr' => null, 'emails' => null, 'last_run_at' => null]]);
    });

    it('counts sends, clicks once per DM, the click rate and collected emails', function () {
        automatedDm(clicked: true);
        automatedDm(clicked: false);
        automatedDm(clicked: false);
        automatedDm(clicked: false);
        automatedDm();
        commentOn(Post::factory()->for($this->channel)->create(), answeredBy: $this->automation);
        Contact::factory()->for($this->owner)->create(['channel_id' => $this->channel->id, 'source_automation_id' => $this->automation->id, 'email' => 'reem@example.com']);

        $this->actingAs($this->owner)->getJson("/api/v1/automations/{$this->automation->id}/stats")
            ->assertOk()
            ->assertJsonPath('data.sends', 5)
            ->assertJsonPath('data.public_replies', 1)
            ->assertJsonPath('data.clicks', 1)
            ->assertJsonPath('data.ctr', 0.25)
            ->assertJsonPath('data.emails', 1);
    });

    it('keeps the stats to the owner, and to automations outside the Trash', function () {
        $this->actingAs(User::factory()->create())->getJson("/api/v1/automations/{$this->automation->id}/stats")->assertForbidden();

        $this->automation->moveToTrash();
        $this->actingAs($this->owner)->getJson("/api/v1/automations/{$this->automation->id}/stats")->assertNotFound();
    });
});

describe('GET /api/v1/stats/overview', function () {
    it('leaves every card empty for a new account', function () {
        $this->actingAs($this->owner)->getJson('/api/v1/stats/overview')
            ->assertOk()
            ->assertJsonPath('data.engagement', null)
            ->assertJsonPath('data.time_saved', null)
            ->assertJsonPath('data.leads', null);
    });

    it('compares the last 7 days with the 7 before', function () {
        $reel = Post::factory()->for($this->channel)->create(['caption' => 'Winter jacket reel']);
        $photo = Post::factory()->for($this->channel)->create(['caption' => 'New abayas']);
        commentOn($reel);
        commentOn($reel, answeredBy: $this->automation);
        commentOn($photo);
        commentOn($photo, postedAt: '-10 days');
        automatedDm();
        automatedDm(sentAt: '-9 days');
        Contact::factory()->for($this->owner)->create(['channel_id' => $this->channel->id, 'email' => 'lead@example.com']);
        Contact::factory()->for($this->owner)->create(['channel_id' => $this->channel->id, 'email' => 'byhand@example.com', 'added_manually' => true]);
        Contact::factory()->for($this->owner)->create(['channel_id' => $this->channel->id, 'phone' => '+966500000000', 'created_at' => now()->subDays(12)]);

        $this->actingAs($this->owner)->getJson('/api/v1/stats/overview')
            ->assertOk()
            ->assertJsonPath('data.engagement.comments', 3)
            ->assertJsonPath('data.engagement.previous', 1)
            ->assertJsonPath('data.engagement.top_posts.0.caption', 'Winter jacket reel')
            ->assertJsonPath('data.engagement.top_posts.0.comments', 2)
            ->assertJsonCount(2, 'data.engagement.top_posts')
            ->assertJsonPath('data.time_saved.replies', 2)
            ->assertJsonPath('data.time_saved.minutes', 2)
            ->assertJsonPath('data.time_saved.previous', 1)
            ->assertJsonPath('data.leads.contacts', 1)
            ->assertJsonPath('data.leads.previous', 1);
    });

    it('counts only the account’s own channels', function () {
        $other = Channel::factory()->create(['platform' => 'instagram']);
        commentOn(Post::factory()->for($other)->create());

        $this->actingAs($this->owner)->getJson('/api/v1/stats/overview')->assertJsonPath('data.engagement', null);
    });
});

it('follows a DM button through the click counter to the owner’s link', function () {
    $dm = automatedDm(clicked: false);

    $this->get(Message::trackedUrl($dm->link_token))->assertRedirect('https://nourboutique.com/abaya');
    $this->get(Message::trackedUrl($dm->link_token))->assertRedirect('https://nourboutique.com/abaya');

    expect($dm->refresh()->link_clicks)->toBe(2)->and($dm->first_clicked_at)->not->toBeNull();
    $this->get('/l/'.str_repeat('a', 32))->assertNotFound();
});

it('shows the plan the owner is on in the dashboard', function () {
    Subscription::factory()->for($this->owner)->for(Plan::factory()->create(['slug' => 'pro']))->create();

    $this->actingAs($this->owner)->getJson('/api/v1/dashboard')->assertJsonPath('data.user.plan', 'pro');
});
