<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Api\V1\ChannelOAuthController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Stripe\StripeClient;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class SystemHealth extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.system-health';

    /** @var array<string, mixed> */
    public array $health = [];

    /** @var array<string, mixed> */
    public array $oauth = [];

    /** @var array<string, string|null> */
    public array $lastWebhooks = [];

    public static function getNavigationLabel(): string
    {
        return __('admin/webhooks.health');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_communication');
    }

    public function mount(): void
    {
        $this->health = app(SystemHealthController::class)->__invoke()->getData(true);
        $this->oauth = app(ChannelOAuthController::class)->status()->getData(true)['data']['providers'] ?? [];

        foreach (['instagram', 'whatsapp', 'messenger', 'tiktok', 'stripe'] as $provider) {
            $this->lastWebhooks[$provider] = WebhookEvent::where('provider', $provider)->latest('created_at')->value('created_at');
        }
    }

    public function driver(): string
    {
        return (string) config('services.channels.driver');
    }

    public function schedulerReady(): bool
    {
        return app()->bound('Illuminate\\Console\\Scheduling\\Schedule');
    }

    /**
     * Read-only launch checks. These expose readiness, never secret values.
     *
     * @return array<string, bool>
     */
    public function launchChecks(): array
    {
        return [
            'production_environment' => app()->isProduction(),
            'debug_disabled' => ! config('app.debug'),
            'instagram_live' => config('services.instagram.driver') !== 'fake' && ! config('services.instagram.allow_fake'),
            'payments_live' => StripeClient::isReadyForProduction(),
            'messaging_live' => config('services.channels.driver') !== 'fake',
            'oauth_live' => config('services.channels.oauth_driver') !== 'fake',
            'ai_live' => config('services.anthropic.driver') !== 'fake' && filled(config('services.anthropic.key')),
            'frontend_https' => str_starts_with((string) config('app.frontend_url'), 'https://'),
            'demo_accounts_absent' => ! User::whereIn('email', ['admin@blexchat.test', 'nour@nourboutique.com'])->exists(),
            'error_monitoring_ready' => $this->errorMonitoringReady(),
            'backup_restore_verified' => $this->backupRestoreVerified(),
            'client_admin_ready' => $this->clientAdminReady(),
        ];
    }

    private function errorMonitoringReady(): bool
    {
        return match (config('app.error_monitoring_provider')) {
            'cloud' => true,
            'nightwatch' => (bool) config('nightwatch.enabled') && filled(config('nightwatch.token')),
            default => false,
        };
    }

    private function backupRestoreVerified(): bool
    {
        if (blank(config('app.backup_restore_verified_at'))) {
            return false;
        }

        try {
            $verifiedAt = Carbon::parse(config('app.backup_restore_verified_at'));

            return $verifiedAt->isPast();
        } catch (\Throwable) {
            return false;
        }
    }

    private function clientAdminReady(): bool
    {
        $email = config('app.client_admin_email');

        return filled($email) && User::query()
            ->where('email', $email)
            ->where('is_admin', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('password')
            ->exists();
    }
}
