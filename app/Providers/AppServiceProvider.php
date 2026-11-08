<?php

namespace App\Providers;

use App\Services\Ai\ClaudeClient;
use App\Services\Ai\FakeClaudeClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Telescope is a dev dependency, so it is registered only on local, where it is installed.
     * Production installs with --no-dev and never loads it.
     */
    public function register(): void
    {
        // Blexchat AI: the fake answers without a key or network, and never in production.
        $this->app->bind(ClaudeClient::class, fn (): ClaudeClient => config('services.anthropic.driver') === 'fake' && ! $this->app->isProduction()
            ? new FakeClaudeClient
            : new ClaudeClient);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
}
