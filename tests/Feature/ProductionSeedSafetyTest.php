<?php

use App\Filament\Pages\SystemHealth;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('seeds required content without demo accounts in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(Plan::count())->toBeGreaterThan(0)
        ->and(User::whereIn('email', ['admin@blexchat.test', 'nour@nourboutique.com'])->exists())->toBeFalse();
});

it('shows failed launch checks without exposing configured secrets', function (): void {
    config([
        'app.debug' => true,
        'app.frontend_url' => 'http://localhost:5173',
        'services.instagram.driver' => 'fake',
        'services.instagram.allow_fake' => true,
        'services.payments.driver' => 'fake',
        'services.payments.allow_fake' => true,
        'services.channels.driver' => 'fake',
        'services.channels.oauth_driver' => 'fake',
        'services.anthropic.driver' => 'claude',
        'services.anthropic.key' => 'never-render-this-key',
        'app.error_monitoring_provider' => null,
        'app.backup_restore_verified_at' => null,
        'app.client_admin_email' => null,
    ]);
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SystemHealth::class)
        ->assertSee(__('admin/webhooks.launch_readiness'))
        ->assertSee(__('admin/webhooks.action_required'))
        ->assertDontSee('never-render-this-key');

    expect($component->instance()->launchChecks())->toMatchArray([
        'production_environment' => false,
        'debug_disabled' => false,
        'instagram_live' => false,
        'payments_live' => false,
        'messaging_live' => false,
        'oauth_live' => false,
        'ai_live' => true,
        'frontend_https' => false,
        'error_monitoring_ready' => false,
        'backup_restore_verified' => false,
        'client_admin_ready' => false,
    ]);
});

it('verifies production operations without exposing the client admin email', function (): void {
    config([
        'app.error_monitoring_provider' => 'cloud',
        'app.backup_restore_verified_at' => '2026-11-19T15:23:13Z',
        'app.client_admin_email' => 'client-admin@example.org',
    ]);
    User::factory()->admin()->create(['email' => 'client-admin@example.org']);
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SystemHealth::class)
        ->assertSee(__('admin/webhooks.error_monitoring_ready'))
        ->assertSee(__('admin/webhooks.backup_restore_verified'))
        ->assertSee(__('admin/webhooks.client_admin_ready'))
        ->assertDontSee('client-admin@example.org');

    expect($component->instance()->launchChecks())->toMatchArray([
        'error_monitoring_ready' => true,
        'backup_restore_verified' => true,
        'client_admin_ready' => true,
    ]);
});

it('requires a verified password-protected admin matching the configured client email', function (array $attributes): void {
    config(['app.client_admin_email' => 'client-admin@example.org']);
    User::factory()->create(['email' => 'client-admin@example.org', ...$attributes]);
    $this->actingAs(User::factory()->admin()->create());

    $checks = Livewire::test(SystemHealth::class)->instance()->launchChecks();

    expect($checks['client_admin_ready'])->toBeFalse();
})->with([
    'not an admin' => [['is_admin' => false]],
    'unverified' => [['is_admin' => true, 'email_verified_at' => null]],
    'no password' => [['is_admin' => true, 'password' => null]],
]);
