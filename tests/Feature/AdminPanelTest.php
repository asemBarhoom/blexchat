<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('renders every admin list page', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/admin', '/admin/channels', '/admin/automations', '/admin/automations/create', '/admin/conversations', '/admin/posts']);

it('keeps shop owners out of the admin panel', function (string $path) {
    $this->actingAs(User::where('email', 'nour@nourboutique.com')->firstOrFail())
        ->get($path)
        ->assertForbidden();
})->with(['/admin', '/admin/channels', '/admin/posts']);

it('refuses admin sign-in for a shop owner', function () {
    $owner = User::where('email', 'nour@nourboutique.com')->firstOrFail();
    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['email' => $owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('never grants admin access through API sign-up', function () {
    auth()->logout();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'Password1!',
        'business_name' => 'Shop',
        'is_admin' => true,
    ])->assertCreated();

    expect(User::where('email', 'sneaky@example.com')->firstOrFail()->is_admin)->toBeFalse();
});

it('renders every admin detail page with seeded data', function () {
    $this->get('/admin/channels/'.Channel::firstOrFail()->id)->assertOk();
    $this->get('/admin/automations/'.Automation::firstOrFail()->id)->assertOk();
    $this->get('/admin/automations/'.Automation::firstOrFail()->id.'/edit')->assertOk();
    $this->get('/admin/conversations/'.Conversation::firstOrFail()->id)->assertOk();
    $this->get('/admin/posts/'.Post::firstOrFail()->id)->assertOk()->assertSee('عباية الشتاء وصلت');
});
