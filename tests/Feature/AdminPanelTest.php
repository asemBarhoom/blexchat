<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('renders every admin list page', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/admin', '/admin/users', '/admin/users/create', '/admin/marketing-home', '/admin/faq-items', '/admin/creators', '/admin/use-cases', '/admin/home-stats',
    '/admin/demo-reply-rules', '/admin/comparison-points',
    '/admin/channels', '/admin/automations', '/admin/conversations', '/admin/posts',
    '/admin/channel-connect-screens', '/admin/channel-connect-screens/create', '/admin/channel-access-requests',
    '/admin/plans', '/admin/survey-questions', '/admin/survey-questions/create', '/admin/survey-answers', '/admin/subscriptions', '/admin/first-moves',
    '/admin/coupons', '/admin/checkout-settings',
    '/admin/email-templates', '/admin/email-templates/1/edit',
    '/admin/channel-options',
    '/admin/auth-screens',
    '/admin/pricing-page', '/admin/pricing-compare-groups', '/admin/pricing-compare-rows', '/admin/pricing-compare-rows/create', '/admin/pricing-faq-items',
    '/admin/automation-templates',
]);

it('keeps shop owners out of the admin panel', function (string $path) {
    $this->actingAs(User::where('email', 'nour@nourboutique.com')->firstOrFail())
        ->get($path)
        ->assertForbidden();
})->with(['/admin', '/admin/users', '/admin/marketing-home', '/admin/channels', '/admin/posts']);

it('no longer offers creating or editing automations from the panel', function () {
    $this->get('/admin/automations/create')->assertNotFound();
    $this->get('/admin/automations/'.Automation::firstOrFail()->id.'/edit')->assertNotFound();
});

it('refuses admin sign-in for a shop owner', function () {
    $owner = User::where('email', 'nour@nourboutique.com')->firstOrFail();
    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['email' => $owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('creates a shop owner without a password, and an admin only with one', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['email' => 'owner@example.com', 'name' => 'Owner', 'business_name' => 'Shop'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'owner@example.com')->firstOrFail())
        ->is_admin->toBeFalse()
        ->password->toBeNull();

    Livewire::test(CreateUser::class)
        ->fillForm(['email' => 'staff@example.com', 'is_admin' => true])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);

    Livewire::test(CreateUser::class)
        ->fillForm(['email' => 'staff@example.com', 'is_admin' => true, 'password' => 'Str0ngPass!'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Hash::check('Str0ngPass!', User::where('email', 'staff@example.com')->firstOrFail()->password))->toBeTrue();
});

it('edits a user and keeps the password when left empty', function () {
    $admin = User::where('email', 'admin@blexchat.test')->firstOrFail();
    $passwordHash = $admin->password;

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['business_name' => 'Blexchat HQ', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh())->business_name->toBe('Blexchat HQ')->password->toBe($passwordHash);
});

it('refuses a duplicate email', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['email' => 'nour@nourboutique.com'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('deletes a user but never the signed-in admin', function () {
    $owner = User::where('email', 'nour@nourboutique.com')->firstOrFail();
    $admin = User::where('email', 'admin@blexchat.test')->firstOrFail();

    $this->get('/admin/users/'.$owner->id)->assertOk()->assertSee('nour@nourboutique.com');

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($admin))
        ->callAction(TestAction::make('delete')->table($owner));

    $this->assertModelMissing($owner);
});

it('renders every admin detail page with seeded data', function () {
    $this->get('/admin/channels/'.Channel::firstOrFail()->id)->assertOk();
    $this->get('/admin/automations/'.Automation::firstOrFail()->id)->assertOk();
    $this->get('/admin/conversations/'.Conversation::firstOrFail()->id)->assertOk();
    $this->get('/admin/posts/'.Post::firstOrFail()->id)->assertOk()->assertSee('عباية الشتاء وصلت');
});
