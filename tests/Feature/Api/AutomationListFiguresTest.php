<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->for($this->owner)->create();
    $this->post = Post::factory()->for($this->channel)->create(['caption' => 'Winter jacket reel', 'thumbnail_url' => 'https://cdn.example.com/reel.jpg']);
    $this->automation = Automation::factory()->for($this->owner)->for($this->channel)->create(['post_id' => $this->post->id]);
});

function sendFrom(Automation $automation, bool $button = false, bool $clicked = false): void
{
    Message::factory()->for(Conversation::factory()->for($automation->channel))->create([
        'direction' => 'outbound', 'automated' => true, 'automation_id' => $automation->id,
        'link_token' => $button ? Str::random(32) : null, 'first_clicked_at' => $clicked ? now() : null,
    ]);
}

it('gives each row of the list its runs and click rate', function () {
    sendFrom($this->automation, button: true, clicked: true);
    sendFrom($this->automation, button: true);
    sendFrom($this->automation);
    Comment::create(['post_id' => $this->post->id, 'channel_id' => $this->channel->id, 'platform_comment_id' => 'c1', 'author_platform_id' => 'u1', 'text' => 'price?', 'automated_reply' => true, 'automation_id' => $this->automation->id, 'posted_at' => now()]);
    $quiet = Automation::factory()->for($this->owner)->for($this->channel)->create();

    $rows = collect($this->actingAs($this->owner)->getJson('/api/v1/automations')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$this->automation->id])->toMatchArray(['runs' => 4, 'ctr' => 0.5])->not->toHaveKey('post')
        ->and($rows[$quiet->id]['runs'])->toBe(0)
        ->and($rows[$quiet->id]['ctr'])->toBe(0);
});

it('counts every row in one query, however long the list', function () {
    Automation::factory()->count(5)->for($this->owner)->for($this->channel)->create()->each(fn (Automation $automation) => sendFrom($automation, button: true));

    DB::enableQueryLog();
    $this->actingAs($this->owner)->getJson('/api/v1/automations')->assertOk();
    $withFive = count(DB::getQueryLog());

    Automation::factory()->count(5)->for($this->owner)->for($this->channel)->create();
    DB::flushQueryLog();
    $this->getJson('/api/v1/automations')->assertOk();

    expect(count(DB::getQueryLog()))->toBe($withFive);
});

it('shows the post the automation watches in its detail, with its figures', function () {
    sendFrom($this->automation, button: true);

    $this->actingAs($this->owner)->getJson("/api/v1/automations/{$this->automation->id}")
        ->assertOk()
        ->assertJsonPath('data.runs', 1)
        ->assertJsonPath('data.ctr', 0)
        ->assertJsonPath('data.post.caption', 'Winter jacket reel')
        ->assertJsonPath('data.post.thumbnail_url', 'https://cdn.example.com/reel.jpg');

    $this->automation->update(['post_id' => null]);
    $this->getJson("/api/v1/automations/{$this->automation->id}")->assertJsonPath('data.post', null);
});
