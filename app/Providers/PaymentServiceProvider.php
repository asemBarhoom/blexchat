<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Stripe\StripeGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Picks the payment provider: PAYMENT_DRIVER=stripe, or fake for development (the default on local
 * and testing). The fake driver reports itself unavailable in production unless PAYMENT_ALLOW_FAKE=true.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn ($app): PaymentGateway => match (config('services.payments.driver')) {
            'fake' => $app->make(FakeGateway::class),
            default => $app->make(StripeGateway::class),
        });
    }
}
