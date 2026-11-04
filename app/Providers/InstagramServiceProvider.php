<?php

namespace App\Providers;

use App\Services\Instagram\FakeInstagramClient;
use App\Services\Instagram\InstagramClient;
use Illuminate\Support\ServiceProvider;

/**
 * Picks how Instagram is reached: INSTAGRAM_DRIVER=meta for the real Graph API, or fake for
 * development (the default on local), where a test page stands in for Instagram's sign-in.
 * The fake client is refused in production unless INSTAGRAM_ALLOW_FAKE=true.
 */
class InstagramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InstagramClient::class, fn (): InstagramClient => FakeInstagramClient::active()
            ? new FakeInstagramClient
            : new InstagramClient);
    }
}
