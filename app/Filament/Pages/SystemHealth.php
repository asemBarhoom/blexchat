<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Api\V1\ChannelOAuthController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Models\User;
use App\Models\WebhookEvent;
use Filament\Pages\Page;

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

        foreach (['instagram', 'whatsapp', 'messenger', 'stripe'] as $provider) {
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
            'payments_live' => config('services.payments.driver') !== 'fake' && ! config('services.payments.allow_fake'),
            'messaging_live' => config('services.channels.driver') !== 'fake',
            'oauth_live' => config('services.channels.oauth_driver') !== 'fake',
            'ai_live' => config('services.anthropic.driver') !== 'fake' && filled(config('services.anthropic.key')),
            'frontend_https' => str_starts_with((string) config('app.frontend_url'), 'https://'),
            'demo_accounts_absent' => ! User::whereIn('email', ['admin@blexchat.test', 'nour@nourboutique.com'])->exists(),
        ];
    }
}
