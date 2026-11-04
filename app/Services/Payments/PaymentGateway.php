<?php

namespace App\Services\Payments;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;

/**
 * Where a paid plan is paid: Paddle Billing, or the fake payment page this app serves in
 * development (PAYMENT_DRIVER). Whatever reports the payment (Paddle's webhook, the fake page's
 * "Pay (test)", or the success page asking) goes through PaymentCheckout::markPaid().
 */
interface PaymentGateway
{
    /**
     * Stored on checkout sessions and subscriptions: `fake` or `paddle`.
     */
    public function name(): string;

    /**
     * Whether payments can be taken: keys are set, or the fake driver is allowed here.
     */
    public function isAvailable(): bool;

    /**
     * Opens a payment page charging $amountCents for the first period, $discountCents of it off by coupon,
     * then the list price every month or year.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckout(User $user, Plan $plan, string $billing, int $amountCents, ?Coupon $coupon, int $discountCents): array;

    /**
     * The checkout as the provider sees it, or null when the provider has nothing to add to what
     * this app stored.
     *
     * @return array{paid: bool, subscription_id: string|null, customer_id: string|null, period_end: string|null}|null
     */
    public function findCheckout(string $id): ?array;

    /**
     * Stops the payments of a subscription at once.
     */
    public function cancelSubscription(string $subscriptionId): void;
}
