<?php

namespace App\Services\Payments;

use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Payments for development, with no keys and no network: the payment page is one this app serves
 * (GET /payments/fake/{session}), behind a signed link. "Pay (test)" reports the session paid the
 * way Stripe's webhook does. Never in production unless PAYMENT_ALLOW_FAKE is true.
 */
class FakeGateway implements PaymentGateway
{
    /** How long the payment page link works, like a Stripe Checkout Session. */
    public const LINK_HOURS = 24;

    public static function allowed(): bool
    {
        return ! app()->isProduction() || (bool) config('services.payments.allow_fake');
    }

    public function isAvailable(): bool
    {
        return self::allowed();
    }

    public function createCheckout(User $user, Plan $plan, string $billing, int $amountCents, ?Coupon $coupon, int $discountCents, string $successUrl, string $cancelUrl): array
    {
        $id = 'fake_cs_'.Str::random(24);

        return ['id' => $id, 'url' => self::pageUrl($id)];
    }

    /**
     * The fake page reports payments itself, so there is nothing to ask.
     */
    public function findCheckout(string $id): ?array
    {
        return null;
    }

    public function periodEnd(string $subscriptionId): ?int
    {
        return null;
    }

    public function cancelSubscription(string $subscriptionId): void {}

    /**
     * The signed link to the payment page. `lang` may be added or changed: it is left out of the signature.
     */
    public static function pageUrl(string $id): string
    {
        // Signed first, so `lang` stays outside the signature.
        return URL::temporarySignedRoute('payments.fake.show', now()->addHours(self::LINK_HOURS), ['session' => $id]).'&lang='.app()->getLocale();
    }

    /**
     * What Stripe's checkout.session.completed carries for a paid session.
     *
     * @return array{id: string, status: string, payment_status: string, subscription: string}
     */
    public static function paidSession(CheckoutSession $session): array
    {
        return [
            'id' => $session->stripe_session_id,
            'status' => 'complete',
            'payment_status' => 'paid',
            'subscription' => 'fake_sub_'.Str::random(14),
        ];
    }
}
