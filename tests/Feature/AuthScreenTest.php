<?php

use App\Filament\Pages\ManageAuthScreens;
use App\Models\AuthScreen;
use App\Models\User;
use Database\Seeders\AuthScreenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AuthScreenSeeder::class);
});

it('serves every auth screen’s texts in English by default', function () {
    $this->getJson('/api/v1/auth/screens')
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('data.signup.title', 'Create your free account')
        ->assertJsonPath('data.signin.submit', 'Email me a sign-in link')
        ->assertJsonPath('data.email_form.email_placeholder', 'name@yourbrand.com')
        ->assertJsonPath('data.check_email.lead_signin', 'We sent a sign-in link to {email}. Open it on this device to finish signing in.')
        ->assertJsonPath('data.verify.title', 'One more step')
        ->assertJsonPath('data.link_expired.submit', 'Send a new link')
        ->assertJsonPath('data.layout.panel_line', 'Turn comments into customers.')
        ->assertJsonPath('meta', ['locale' => 'en', 'fallback' => false, 'available_locales' => ['en', 'ar']]);
});

it('serves Arabic by lang or Accept-Language', function () {
    $this->getJson('/api/v1/auth/screens?lang=ar')
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data.signup.title', 'أنشئ حسابك المجاني')
        ->assertJsonPath('meta.locale', 'ar')
        ->assertJsonPath('meta.fallback', false);

    $this->getJson('/api/v1/auth/screens', ['Accept-Language' => 'ar-SA'])
        ->assertJsonPath('data.verify.title', 'خطوة أخيرة');
});

it('returns 404 when the texts have not been seeded', function () {
    AuthScreen::query()->delete();

    $this->getJson('/api/v1/auth/screens')->assertNotFound();
});

it('keeps the texts when the seeder runs again', function () {
    $this->seed(AuthScreenSeeder::class);

    expect(AuthScreen::count())->toBe(7);
});

it('saves edited texts from the admin page, and a cleared Arabic text falls back to English', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageAuthScreens::class)
        ->fillForm([
            'signup.en.title' => 'Start free today',
            'signup.ar.title' => 'ابدأ مجانًا اليوم',
            'signin.ar.lead' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->getJson('/api/v1/auth/screens')
        ->assertJsonPath('data.signup.title', 'Start free today');

    $this->getJson('/api/v1/auth/screens?lang=ar')
        ->assertJsonPath('data.signup.title', 'ابدأ مجانًا اليوم')
        ->assertJsonPath('data.signin.lead', 'Sign in with Google, or we’ll email you a link. No password to remember.')
        ->assertJsonPath('meta.fallback', true);
});

it('requires every English text but no Arabic one', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageAuthScreens::class)
        ->fillForm(['verify.en.title' => '', 'verify.ar.title' => ''])
        ->call('save')
        ->assertHasFormErrors(['verify.en.title' => 'required'])
        ->assertHasNoFormErrors(['verify.ar.title']);
});

it('saves without changing anything when nothing was edited', function () {
    $this->actingAs(User::factory()->admin()->create());
    $before = AuthScreen::all()->mapWithKeys(fn (AuthScreen $screen): array => [$screen->key => $screen->getTranslations('texts')])->all();

    Livewire::test(ManageAuthScreens::class)->call('save')->assertHasNoFormErrors();

    expect(AuthScreen::all()->mapWithKeys(fn (AuthScreen $screen): array => [$screen->key => $screen->getTranslations('texts')])->all())->toBe($before);
});
