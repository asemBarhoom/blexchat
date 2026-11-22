<?php

use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Filament\Resources\WebhookEvents\Pages\ViewWebhookEvent;
use App\Filament\Resources\WebhookEvents\WebhookEventResource;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets an admin list and view webhook events without write actions', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $event = WebhookEvent::factory()->create(['provider' => 'messenger', 'status' => 'rejected', 'error' => 'invalid_signature', 'payload' => ['secret' => 'never show']]);

    Livewire::test(ListWebhookEvents::class)->assertCanSeeTableRecords([$event]);
    Livewire::test(ViewWebhookEvent::class, ['record' => $event->getRouteKey()])->assertSee('invalid_signature');
    expect(WebhookEventResource::canCreate())->toBeFalse()
        ->and(WebhookEventResource::canDelete(null))->toBeFalse();
});

it('blocks non-admins from the webhook resource and health page', function (): void {
    $this->actingAs(User::factory()->create()->forceFill(['is_admin' => false]));

    $this->get('/admin/webhook-events')->assertForbidden();
    $this->get('/admin/system-health')->assertForbidden();
});

it('shows health checks, provider readiness and no secret values', function (): void {
    config(['services.channels.oauth_driver' => 'provider', 'services.channels.driver' => 'fake', 'services.messenger.client_id' => 'configured', 'services.whatsapp.client_id' => null, 'services.tiktok.client_id' => null, 'services.channels.oauth_redirect_url' => 'https://example.test/callback']);
    WebhookEvent::factory()->create(['provider' => 'instagram', 'payload' => ['access_token' => 'do-not-render']]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(SystemHealth::class)
        ->assertSee('Messenger')
        ->assertSee(__('admin/webhooks.configured'))
        ->assertSee(__('admin/webhooks.not_configured'))
        ->assertDontSee('do-not-render');
});

it('reports production email as degraded until a real mailer is configured', function (): void {
    app()->instance('env', 'production');
    config(['mail.default' => 'log']);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.mail', false);
});

it('reports a configured production email provider without exposing its key', function (): void {
    app()->instance('env', 'production');
    config([
        'mail.default' => 'postmark',
        'mail.from.address' => 'hello@blexchat.com',
        'services.postmark.key' => 'server-secret',
    ]);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.mail', 'postmark')
        ->assertDontSee('server-secret');
});
