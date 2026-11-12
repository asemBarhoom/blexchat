<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Api\V1\ChannelOAuthController;
use App\Http\Controllers\Api\V1\SystemHealthController;
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
}
