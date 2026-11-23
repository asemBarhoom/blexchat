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

it('does not treat the default local smtp host as production ready', function (): void {
    app()->instance('env', 'production');
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.from.address' => 'hello@blexchat.com',
    ]);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('checks.mail', false);
});

it('reports a configured production email provider without exposing its key', function (): void {
    app()->instance('env', 'production');
    config([
        'mail.default' => 'postmark',
        'mail.from.address' => 'hello@blexchat.com',
        'services.postmark.key' => 'server-secret',
        'services.payments.driver' => 'stripe',
        'services.stripe.secret' => 'sk_live_configured',
        'services.stripe.webhook_secret' => 'whsec_configured',
        'services.stripe.currency' => 'usd',
    ]);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.mail', 'postmark')
        ->assertDontSee('server-secret');
});

it('reports production payments as degraded when fake or test Stripe is configured', function (array $paymentConfig): void {
    app()->instance('env', 'production');
    config($paymentConfig);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.payments', false);
})->with([
    'fake payments' => [[
        'services.payments.driver' => 'fake',
        'services.payments.allow_fake' => true,
    ]],
    'Stripe test mode' => [[
        'services.payments.driver' => 'stripe',
        'services.payments.allow_fake' => false,
        'services.stripe.secret' => 'sk_test_configured',
        'services.stripe.webhook_secret' => 'whsec_configured',
        'services.stripe.currency' => 'usd',
    ]],
    'missing webhook secret' => [[
        'services.payments.driver' => 'stripe',
        'services.payments.allow_fake' => false,
        'services.stripe.secret' => 'sk_live_configured',
        'services.stripe.webhook_secret' => null,
        'services.stripe.currency' => 'usd',
    ]],
]);

it('reports live Stripe payments ready without exposing either secret', function (): void {
    app()->instance('env', 'production');
    config([
        'services.payments.driver' => 'stripe',
        'services.payments.allow_fake' => false,
        'services.stripe.secret' => 'sk_live_server-secret',
        'services.stripe.webhook_secret' => 'whsec_server-secret',
        'services.stripe.currency' => 'usd',
    ]);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('checks.payments', 'stripe')
        ->assertDontSee('sk_live_server-secret')
        ->assertDontSee('whsec_server-secret');
});
