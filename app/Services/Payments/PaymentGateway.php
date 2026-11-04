<?php

namespace App\Services\Payments;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;

/**
 * Where a paid plan is paid: Stripe Checkout in production, or the fake payment page this app
 * serves in development (PAYMENT_DRIVER). Both describe a paid session the way Stripe does, so
 * PaymentCheckout puts the plan in place by one code path.
 */
interface PaymentGateway
{
    /**
     * Whether payments can be taken: keys are set, or the fake driver is allowed here.
     */
    public function isAvailable(): bool;

    /**
     * Opens a payment page charging $amountCents for the first period, $discountCents of it off by coupon.
     * $successUrl holds the literal {CHECKOUT_SESSION_ID}, as Stripe expects.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckout(User $user, Plan $plan, string $billing, int $amountCents, ?Coupon $coupon, int $discountCents, string $successUrl, string $cancelUrl): array;

    /**
     * The session as the provider sees it, Stripe-shaped (`id`, `status`, `payment_status`, `subscription`),
     * or null when the provider has nothing to add to what this app stored.
     *
     * @return array<string, mixed>|null
     */
    public function findCheckout(string $id): ?array;

    /**
     * Unix time the first paid period of a subscription ends, or null when unknown.
     */
    public function periodEnd(string $subscriptionId): ?int;

    /**
     * Stops the payments of a subscription at once.
     */
    public function cancelSubscription(string $subscriptionId): void;
}
