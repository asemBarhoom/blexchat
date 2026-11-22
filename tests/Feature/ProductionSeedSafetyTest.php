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
    ]);
});
