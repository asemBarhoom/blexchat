<?php

use App\Models\User;
use Database\Seeders\MarketingHomeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $compiledViews = sys_get_temp_dir().'/blexchat-test-views';
    if (! is_dir($compiledViews)) {
        mkdir($compiledViews, 0777, true);
    }
    config(['view.compiled' => $compiledViews]);
});

it('returns JSON 401 for unauthenticated API requests without an Accept header', function () {
    $this->get('/api/v1/channels')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('serves the seeded home page without authentication', function () {
    $this->seed(MarketingHomeSeeder::class);

    $this->get('/api/v1/home')
        ->assertOk()
        ->assertJsonPath('data.sections.0.id', 'hero')
        ->assertJsonPath('data.sections.1.id', 'creators')
        ->assertJsonPath('data.sections.2.id', 'intro')
        ->assertJsonPath('data.sections.3.type', 'vertical_slider')
        ->assertJsonPath('data.sections.5.heading', 'Built for the way YOU sell')
        ->assertJsonCount(12, 'data.sections')
        ->assertJsonPath('data.sections.0.story.pressure.count', 47)
        ->assertJsonPath('data.sections.0.composer.label', 'Try it live')
        ->assertJsonPath('data.sections.0.composer.examples.1', 'Do you ship to Riyadh?')
        ->assertJsonPath('data.demo_replies.rules.1.reply', 'Yes! Free delivery across KSA 🚚')
        ->assertJsonPath('data.demo_replies.fallback.action', 'View product')
        ->assertJsonPath('data.sections.0.photos.relax', url('/storage/marketing/home/hero-photo.webp'))
        ->assertJsonPath('data.sections.7.tabs.4.scene.counter.value', '1,284')
        ->assertJsonPath('data.sections.7.tabs.1.scene.lead.name', 'Omar K.')
        ->assertJsonPath('data.sections.8.replies_from', 'demo_replies')
        ->assertJsonPath('data.footer.giant_label', 'Made for creators, brands, sellers and agencies')
        ->assertJsonStructure(['data' => ['navigation', 'sections', 'demo_replies', 'footer'], 'meta']);
});

it('falls back to English and says so when the requested language has no content', function () {
    $this->seed(MarketingHomeSeeder::class);

    $this->get('/api/v1/home?lang=ar')
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonPath('meta.requested_locale', 'ar')
        ->assertJsonPath('meta.fallback', true)
        ->assertJsonPath('meta.available_locales', ['en']);
});

it('returns a 404 when the marketing home has not been seeded', function () {
    $this->get('/api/v1/home')->assertNotFound();
});

it('redirects unauthenticated admin visitors to the Filament login', function () {
    $this->get('/admin/posts')->assertRedirect('/admin/login');
});

it('renders the Filament post details page', function () {
    $user = User::factory()->create();
    $channel = $user->channels()->create([
        'platform' => 'instagram',
        'account_id' => 'test-account',
        'access_token' => 'test-token',
    ]);
    $post = $channel->posts()->create([
        'platform_post_id' => 'test-post',
        'caption' => 'Test post',
        'media_type' => 'image',
    ]);

    $this->actingAs($user)->get("/admin/posts/{$post->id}")
        ->assertOk()
        ->assertSee('Test post');
});
