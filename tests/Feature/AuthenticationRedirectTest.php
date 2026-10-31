<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns JSON 401 for unauthenticated API requests without an Accept header', function () {
    $this->get('/api/v1/dashboard')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('redirects unauthenticated admin visitors to the Filament login', function () {
    $this->get('/admin/posts')->assertRedirect('/admin/login');
});

it('renders the Filament post details page', function () {
    $user = User::factory()->admin()->create();
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
