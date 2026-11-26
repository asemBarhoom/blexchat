<?php

use App\Models\Automation;
use App\Models\AutomationTemplate;
use App\Models\Channel;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\AutomationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.instagram.graph_version' => 'v21.0']);

    $this->owner = User::factory()->create();
    $this->channel = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'whatsapp']);
    $this->post = Post::factory()->create(['channel_id' => $this->channel->id]);
});

/**
 * What the wizard sends on Review for t1, a comment automation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function wizardAnswers(array $overrides = []): array
{
    return [
        'name' => 'Comment a word → send the catalogue',
        'channel_id' => test()->channel->id,
        'kind' => 'comment',
        'post_mode' => 'specific',
        'post_id' => test()->post->id,
        'trigger' => 'words',
        'words' => ['كتالوج'],
        'public_reply' => true,
        'public_reply_text' => 'أرسلنا لك التفاصيل بالخاص 💌',
        'dm_text' => 'هلا والله! 👋 هذا كتالوج الموسم كامل:',
        'dm_button' => 'افتحي الكتالوج',
        'link_url' => 'https://nourboutique.com/abaya-winter',
        ...$overrides,
    ];
}

function onPaidPlan(User $owner, bool $hasEmailCapture = true): void
{
    Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => Plan::factory()->create(['has_email_capture' => $hasEmailCapture])->id, 'status' => 'active']);
    $owner->unsetRelation('subscription');
}

describe('GET /api/v1/automation-templates', function () {
    it('serves the shelf in both languages with the starters by trigger', function () {
        $this->seed(AutomationTemplateSeeder::class);
        AutomationTemplate::factory()->inactive()->create(['key' => 'hidden']);

        $response = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/automation-templates?lang=ar')->assertOk();

        expect($response->json('data.*.id'))->toBe(['t1', 't2', 't3', 't4', 't5', 't6', 't7', 't8', 't9']);
        $response
            ->assertJsonPath('data.0.name', 'تعليق بكلمة ← إرسال الكتالوج')
            ->assertJsonPath('data.0.draft.words', ['كتالوج'])
            ->assertJsonPath('data.0.draft.public_reply', true)
            ->assertJsonPath('data.1.draft.trigger', 'any')
            ->assertJsonPath('data.1.draft.public_reply', false)
            ->assertJsonPath('data.3.draft', null)
            ->assertJsonPath('data.3.locked', true)
            ->assertJsonPath('starters.dm.name', 'الرد على كل رسائلك الخاصة')
            ->assertJsonPath('starters.comment.draft.dm_button', 'شوفي المنتج');

        $this->getJson('/api/v1/automation-templates')->assertJsonPath('data.0.name', 'Comment a word → send the catalogue');
    });

    it('needs a signed-in owner', function () {
        $this->getJson('/api/v1/automation-templates')->assertUnauthorized();
    });
});

describe('GET /api/v1/channels/{channel}/posts', function () {
    it('lists the channel’s posts newest first', function () {
        Post::factory()->create(['channel_id' => $this->channel->id, 'posted_at' => now()->subYear()]);
        $newest = Post::factory()->create(['channel_id' => $this->channel->id, 'posted_at' => now()]);
        Post::factory()->create();

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$this->channel->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.synced', false)
            ->assertJsonPath('data.0.id', $newest->id);
    });

    it('brings in the latest posts from Instagram, and still answers when Instagram fails', function () {
        $instagram = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'instagram', 'status' => 'active', 'access_token' => 'IGAA']);
        Http::fake(['graph.instagram.com/v21.0/me/media*' => Http::sequence()
            ->push(['data' => [['id' => 'm1', 'caption' => 'ريل جديد', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'thumbnail_url' => 'https://cdn.example.com/m1.jpg', 'permalink' => 'https://www.instagram.com/reel/m1/', 'like_count' => 1204, 'comments_count' => 4, 'timestamp' => '2026-11-01T10:00:00+0000']]])
            ->push(['error' => ['message' => 'Expired']], 400)]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$instagram->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', true)
            ->assertJsonPath('data.0.platform_post_id', 'm1')
            ->assertJsonPath('data.0.media_type', 'reel')
            ->assertJsonPath('data.0.permalink', 'https://www.instagram.com/reel/m1/')
            ->assertJsonPath('data.0.likes', 1204)
            ->assertJsonPath('data.0.comments', 4);

        $this->getJson("/api/v1/channels/{$instagram->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', false)
            ->assertJsonPath('meta.needs_reconnect', false)
            ->assertJsonPath('meta.total', 1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer IGAA'));
        expect($instagram->fresh()->status)->toBe('active');
    });

    it('brings in TikTok videos from TikTok for Business', function () {
        $tiktok = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'tiktok', 'account_id' => 'tt-open-id', 'status' => 'active', 'access_token' => 'TTAA', 'token_expires_at' => now()->addDay()]);
        Http::fake(['business-api.tiktok.com/open_api/v1.3/business/video/list/*' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['videos' => [[
            'item_id' => '6990565363377392901',
            'caption' => 'New collection',
            'thumbnail_url' => 'https://cdn.example.com/cover.jpg',
            'share_url' => 'https://www.tiktok.com/@nour/video/6990565363377392901',
            'create_time' => '1793527200',
            'likes' => 501,
            'comments' => 23,
        ]], 'has_more' => false]])]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$tiktok->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', true)
            ->assertJsonPath('data.0.platform_post_id', '6990565363377392901')
            ->assertJsonPath('data.0.media_type', 'video')
            ->assertJsonPath('data.0.caption', 'New collection')
            ->assertJsonPath('data.0.permalink', 'https://www.tiktok.com/@nour/video/6990565363377392901')
            ->assertJsonPath('data.0.likes', 501)
            ->assertJsonPath('data.0.comments', 23);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Access-Token', 'TTAA') && $request['business_id'] === 'tt-open-id');
    });

    it('brings in the Facebook Page’s posts, filling in a post a comment already brought', function () {
        $page = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'messenger', 'account_id' => 'page-1', 'status' => 'active', 'access_token' => 'PAGE']);
        $fromComment = Post::factory()->create(['channel_id' => $page->id, 'platform_post_id' => 'page-1_post-1', 'caption' => null, 'posted_at' => null]);
        Http::fake(['graph.facebook.com/v21.0/page-1/posts*' => Http::response(['data' => [[
            'id' => 'page-1_post-1',
            'message' => 'منشور اختبار',
            'full_picture' => 'https://scontent.example.com/p1.jpg',
            'permalink_url' => 'https://www.facebook.com/page-1/posts/post-1',
            'status_type' => 'added_photos',
            'created_time' => '2026-11-24T10:00:00+0000',
            'reactions' => ['data' => [], 'summary' => ['total_count' => 12]],
            'comments' => ['data' => [], 'summary' => ['total_count' => 6]],
        ]]])]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$page->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $fromComment->id)
            ->assertJsonPath('data.0.caption', 'منشور اختبار')
            ->assertJsonPath('data.0.media_type', 'image')
            ->assertJsonPath('data.0.thumbnail_url', 'https://scontent.example.com/p1.jpg')
            ->assertJsonPath('data.0.permalink', 'https://www.facebook.com/page-1/posts/post-1')
            ->assertJsonPath('data.0.likes', 12)
            ->assertJsonPath('data.0.comments', 6);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer PAGE'));
    });

    it('renews a TikTok token about to end before listing videos', function () {
        $tiktok = Channel::factory()->create([
            'user_id' => $this->owner->id,
            'platform' => 'tiktok',
            'account_id' => 'tt-open-id',
            'status' => 'active',
            'access_token' => 'old-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addMinutes(5),
        ]);
        Http::fake([
            'business-api.tiktok.com/open_api/v1.3/tt_user/oauth2/refresh_token/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['access_token' => 'new-token', 'refresh_token' => 'new-refresh', 'expires_in' => 86400]]),
            'business-api.tiktok.com/open_api/v1.3/business/video/list/*' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['videos' => []]]),
        ]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$tiktok->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', true)
            ->assertJsonPath('meta.needs_reconnect', false);

        expect($tiktok->fresh())
            ->access_token->toBe('new-token')
            ->refresh_token->toBe('new-refresh')
            ->token_expires_at->isAfter(now()->addHours(23))->toBeTrue();
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'tt_user/oauth2/refresh_token/') && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-token');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'business/video/list/') && $request->hasHeader('Access-Token', 'new-token'));
    });

    it('asks for a reconnect when Instagram refuses the token, and stops asking Instagram', function () {
        $instagram = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'instagram', 'status' => 'active', 'access_token' => 'IGAA']);
        Http::fake(['graph.instagram.com/v21.0/me/media*' => Http::response(['error' => ['message' => 'Error validating access token', 'type' => 'OAuthException', 'code' => 190]], 400)]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$instagram->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', false)
            ->assertJsonPath('meta.needs_reconnect', true);

        expect($instagram->fresh()->status)->toBe('expired');

        $this->getJson("/api/v1/channels/{$instagram->id}/posts")->assertOk()->assertJsonPath('meta.needs_reconnect', true);
        Http::assertSentCount(1);
    });

    it('keeps the long thumbnail links that Instagram’s CDN gives', function () {
        $instagram = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'instagram', 'status' => 'active', 'access_token' => 'IGAA']);
        $thumbnail = 'https://scontent.cdninstagram.com/v/t51.2885-15/'.str_repeat('x', 600);
        Http::fake(['graph.instagram.com/v21.0/me/media*' => Http::response(['data' => [['id' => 'm9', 'media_type' => 'IMAGE', 'media_url' => $thumbnail, 'thumbnail_url' => $thumbnail]]])]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$instagram->id}/posts")
            ->assertOk()
            ->assertJsonPath('meta.synced', true)
            ->assertJsonPath('data.0.thumbnail_url', $thumbnail);
    });

    it('marks the channel as needing a reconnect when Instagram rejects its token', function () {
        $instagram = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'instagram', 'status' => 'active', 'access_token' => 'IGAA']);
        Http::fake(['graph.instagram.com/v21.0/me/media*' => Http::response(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], 401)]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$instagram->id}/posts")->assertOk()->assertJsonPath('meta.synced', false);

        expect($instagram->fresh()->status)->toBe('expired');
    });

    it('keeps the channel active when Instagram fails for another reason', function () {
        $instagram = Channel::factory()->create(['user_id' => $this->owner->id, 'platform' => 'instagram', 'status' => 'active', 'access_token' => 'IGAA']);
        Http::fake(['graph.instagram.com/v21.0/me/media*' => Http::response(['error' => ['message' => 'Rate limited', 'code' => 4]], 400)]);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/channels/{$instagram->id}/posts")->assertOk()->assertJsonPath('meta.synced', false);

        expect($instagram->fresh()->status)->toBe('active');
    });

    it('refuses another account’s channel', function () {
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/channels/'.Channel::factory()->create()->id.'/posts')->assertForbidden();
    });
});

describe('POST /api/v1/automations/wizard', function () {
    it('saves a comment automation live, with the runner’s trigger and actions', function () {
        $this->seed(AutomationTemplateSeeder::class);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers(['template' => 't1']))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'comment')
            ->assertJsonPath('data.trigger_type', 'comment_keyword')
            ->assertJsonPath('data.template', 't1')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.wizard.post_id', $this->post->id)
            ->assertJsonPath('data.wizard.link_url', 'https://nourboutique.com/abaya-winter')
            ->assertJsonPath('data.wizard.ask_follow', false);

        $automation = Automation::sole();
        expect($automation->keywords)->toBe(['كتالوج'])
            ->and($automation->actions)->toBe([
                ['type' => 'reply_comment', 'message' => 'أرسلنا لك التفاصيل بالخاص 💌'],
                ['type' => 'send_dm_with_button', 'message' => 'هلا والله! 👋 هذا كتالوج الموسم كامل:', 'button_label' => 'افتحي الكتالوج', 'button_url' => 'https://nourboutique.com/abaya-winter'],
            ]);
    });

    it('saves a DM automation that answers every message, without a post or public reply', function () {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers([
            'kind' => 'dm', 'trigger' => 'any', 'post_mode' => 'any', 'public_reply' => true, 'link_url' => null,
        ]))->assertCreated()->assertJsonPath('data.wizard.trigger', 'any');

        expect(Automation::sole())
            ->trigger_type->toBe('dm_keyword')
            ->post_mode->toBeNull()
            ->post_id->toBeNull()
            ->keywords->toBe([])
            ->actions->toBe([['type' => 'send_dm', 'message' => 'هلا والله! 👋 هذا كتالوج الموسم كامل:', 'button_label' => 'افتحي الكتالوج']]);
    });

    it('validates the answers', function (array $overrides, string $error) {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);
    })->with([
        'unknown kind' => [['kind' => 'ad'], 'kind'],
        'another account’s channel' => [fn () => ['channel_id' => Channel::factory()->create()->id], 'channel_id'],
        'post of another channel' => [fn () => ['post_id' => Post::factory()->create()->id], 'post_id'],
        'no post picked' => [['post_id' => null], 'post_id'],
        'words without a word' => [['words' => []], 'words'],
        'repeated word' => [['words' => ['السعر', 'السعر']], 'words.1'],
        'public reply without text' => [['public_reply_text' => ''], 'public_reply_text'],
        'empty DM' => [['dm_text' => ''], 'dm_text'],
        'DM over 640' => [['dm_text' => str_repeat('a', 641)], 'dm_text'],
        'button over 20' => [['dm_button' => str_repeat('a', 21)], 'dm_button'],
        'link without a button' => [['dm_button' => null], 'dm_button'],
        'not a link' => [['link_url' => 'javascript:alert(1)'], 'link_url'],
        'follow-up without a wait' => [['follow_up' => true, 'follow_up_text' => 'Still thinking?'], 'follow_up_after'],
        'unknown template' => [['template' => 'nope'], 'template'],
    ]);

    it('keeps paid answers for paid plans', function () {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard?lang=ar', wizardAnswers([
            'post_mode' => 'next', 'ask_email' => true, 'email_ask' => 'عطينا إيميلك',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['post_mode', 'ask_email'])
            ->assertJsonPath('errors.ask_email.0', __('automation.email_capture_extra', locale: 'ar'));

        onPaidPlan($this->owner);

        $this->postJson('/api/v1/automations/wizard', wizardAnswers([
            'post_mode' => 'next', 'ask_email' => true, 'email_ask' => 'عطينا إيميلك', 'follow_up' => true, 'follow_up_after' => '6h', 'follow_up_text' => 'لسا تفكرين فيها؟',
        ]))->assertCreated()
            ->assertJsonPath('data.post_mode', 'next')
            ->assertJsonPath('data.post_id', null)
            ->assertJsonPath('data.wizard.email_ask', 'عطينا إيميلك')
            ->assertJsonPath('data.wizard.follow_up_after', '6h');
    });

    it('keeps the email question for plans with email capture', function () {
        onPaidPlan($this->owner, hasEmailCapture: false);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers([
            'post_mode' => 'next', 'ask_email' => true, 'email_ask' => 'Your email?', 'follow_up' => true, 'follow_up_after' => '1h', 'follow_up_text' => 'Still thinking?',
        ]))->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['ask_email'])
            ->assertJsonPath('errors.ask_email.0', __('automation.email_capture_extra'));
    });
});

describe('PUT /api/v1/automations/{id}/wizard', function () {
    it('saves an edit, keeping the kind and channel', function () {
        $automation = Automation::factory()->create(['user_id' => $this->owner->id, 'channel_id' => $this->channel->id]);
        $automation->update(Automation::fromWizard(wizardAnswers()));

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/automations/{$automation->id}/wizard", wizardAnswers([
            'kind' => 'dm', 'channel_id' => Channel::factory()->create()->id, 'words' => ['السعر', 'بكم'], 'public_reply' => false,
        ]))->assertOk()
            ->assertJsonPath('data.kind', 'comment')
            ->assertJsonPath('data.channel_id', $this->channel->id)
            ->assertJsonPath('data.keywords', ['السعر', 'بكم'])
            ->assertJsonPath('data.wizard.public_reply', false);
    });

    it('lets an extra saved during a trial stay, but not a new one', function () {
        $automation = Automation::factory()->create(['user_id' => $this->owner->id, 'channel_id' => $this->channel->id]);
        $automation->update(Automation::fromWizard(wizardAnswers(['ask_email' => true, 'email_ask' => 'عطينا إيميلك'])));

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/automations/{$automation->id}/wizard", wizardAnswers(['ask_email' => true, 'email_ask' => 'إيميلك؟']))
            ->assertOk();

        $this->putJson("/api/v1/automations/{$automation->id}/wizard", wizardAnswers(['ask_email' => true, 'email_ask' => 'إيميلك؟', 'ask_follow' => true, 'follow_text' => 'تابعينا', 'follow_button' => 'تابعت']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ask_follow'])
            ->assertJsonMissingValidationErrors(['ask_email']);
    });

    it('refuses another account’s automation', function () {
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/automations/'.Automation::factory()->create()->id.'/wizard', wizardAnswers())
            ->assertForbidden();
    });
});

describe('going live (W15 "Couldn’t go live")', function () {
    it('refuses an automation on a disconnected channel, and starts it once the channel is back', function () {
        $this->channel->update(['status' => 'expired']);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'channel_disconnected')
            ->assertJsonPath('channel', 'whatsapp');
        expect(Automation::count())->toBe(0);

        $this->channel->update(['status' => 'active']);
        $this->postJson('/api/v1/automations/wizard', wizardAnswers())->assertCreated();
    });

    it('refuses an automation on a platform whose messaging is not yet live (TikTok)', function () {
        $tikTokChannel = Channel::factory()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $this->owner->workspaces()->first()->id,
            'platform' => 'tiktok',
            'status' => 'active',
        ]);
        $post = Post::factory()->create(['channel_id' => $tikTokChannel->id]);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers([
            'channel_id' => $tikTokChannel->id,
            'post_id' => $post->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'channel_not_live')
            ->assertJsonPath('channel', 'tiktok');
        expect(Automation::count())->toBe(0);

        $automation = Automation::factory()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $this->owner->workspaces()->first()->id,
            'channel_id' => $tikTokChannel->id,
            'active' => false,
        ]);

        $this->patchJson("/api/v1/automations/{$automation->id}/toggle", ['active' => true])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'channel_not_live')
            ->assertJsonPath('channel', 'tiktok');
    });

    it('keeps Free to its live automations, counting only the live ones', function () {
        Plan::factory()->free()->create();
        Automation::factory()->count(3)->create(['user_id' => $this->owner->id, 'channel_id' => $this->channel->id, 'active' => true]);
        $stopped = Automation::factory()->create(['user_id' => $this->owner->id, 'channel_id' => $this->channel->id, 'active' => false]);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'live_limit')
            ->assertJsonPath('limit', 3);
        $this->patchJson("/api/v1/automations/{$stopped->id}/toggle", ['active' => true])->assertUnprocessable()->assertJsonPath('code', 'live_limit');

        Automation::where('active', true)->first()->update(['active' => false]);
        $this->patchJson("/api/v1/automations/{$stopped->id}/toggle", ['active' => true])->assertOk()->assertJsonPath('data.active', true);
    });

    it('puts no limit on a plan without one', function () {
        onPaidPlan($this->owner);
        Automation::factory()->count(5)->create(['user_id' => $this->owner->id, 'channel_id' => $this->channel->id, 'active' => true]);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/automations/wizard', wizardAnswers())->assertCreated();
    });
});
