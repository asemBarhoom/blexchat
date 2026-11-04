<?php

namespace App\Services\Payments;

use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paid plans through a payment page: Paddle Billing, or the fake page in development (PaymentGateway).
 * Prices come from the plans table. Every way a payment is reported ends in markPaid(), renew() or
 * ended(), and each of them can run twice and changes nothing more.
 */
class PaymentCheckout
{
    public function __construct(private PaymentGateway $gateway) {}

    public function isAvailable(): bool
    {
        return $this->gateway->isAvailable();
    }

    /**
     * Where the payment page sends the visitor after paying; the success page passes `session_id`
     * to GET /checkout/session/{id}.
     */
    public static function successUrl(string $sessionId): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/app?checkout=success&session_id='.urlencode($sessionId);
    }

    /**
     * Where closing the payment page (or "Decline" on the fake page) goes, with nothing charged.
     */
    public static function cancelUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/app?checkout=cancelled';
    }

    /**
     * Opens a payment page for the plan, renewing monthly or yearly. The first period costs the
     * coupon quote's `billed_total`; renewals cost the list price.
     */
    public function start(User $user, Plan $plan, string $billing, ?Coupon $coupon = null): CheckoutSession
    {
        $months = $billing === 'annual' ? 12 : 1;
        $listCents = $plan->priceFor($billing) * 100;
        $amountCents = $listCents * $months;
        $discountCents = $coupon === null ? 0 : $coupon->discountCents($listCents) * $months;

        $checkout = $this->gateway->createCheckout($user, $plan, $billing, $amountCents, $discountCents > 0 ? $coupon : null, $discountCents);

        return $user->checkoutSessions()->create([
            'payment_provider' => $this->gateway->name(),
            'plan_id' => $plan->id,
            'coupon_id' => $discountCents > 0 ? $coupon->id : null,
            'billing_period' => $billing,
            'payment_session_id' => $checkout['id'],
            'amount_total' => $amountCents - $discountCents,
            'currency' => (string) config('services.payments.currency'),
            'url' => $checkout['url'],
            'status' => 'open',
        ]);
    }

    /**
     * Asks the provider how a checkout ended and acts on it, for the success page when the webhook
     * has not arrived yet.
     */
    public function refresh(CheckoutSession $session): CheckoutSession
    {
        if ($session->status === 'open' && $session->payment_provider === $this->gateway->name() && $this->gateway->isAvailable()) {
            $found = $this->gateway->findCheckout($session->payment_session_id);

            if ($found !== null && $found['paid'] && $found['subscription_id'] !== null) {
                $this->markPaid($session, $found['subscription_id'], $found['period_end'], $found['customer_id']);
            }
        }

        return $session->refresh();
    }

    /**
     * The one path that puts a paid plan in place: the plan, the billing period, active, the paid
     * period's end, and the provider's ids. Once, even when the webhook and the success page report
     * the payment at the same moment. A paid plan it replaces is cancelled with the provider.
     *
     * @param  string|null  $periodEnd  When the first paid period ends (ISO 8601); a month or a year from now when unknown.
     */
    public function markPaid(CheckoutSession $session, string $subscriptionId, ?string $periodEnd = null, ?string $customerId = null): void
    {
        $replaced = DB::transaction(function () use ($session, $subscriptionId, $periodEnd, $customerId): ?Subscription {
            $session = CheckoutSession::whereKey($session->id)->lockForUpdate()->first();

            if ($session->isComplete()) {
                return null;
            }

            $user = $session->user;
            $current = $user->subscription;
            $replaced = $current?->paidByCard() && $current->payment_subscription_id !== $subscriptionId ? $current->replicate() : null;

            $user->subscription()->updateOrCreate([], [
                'plan_id' => $session->plan_id,
                'billing_period' => $session->billing_period,
                'status' => 'active',
                'trial_ends_at' => null,
                'payment_provider' => $session->payment_provider,
                'payment_subscription_id' => $subscriptionId,
                'current_period_end' => $periodEnd !== null ? Carbon::parse($periodEnd) : ($session->billing_period === 'annual' ? now()->addYear() : now()->addMonth()),
            ]);
            $session->update(['status' => 'complete', 'payment_subscription_id' => $subscriptionId, 'completed_at' => now()]);
            $session->coupon?->redeem();

            if ($customerId !== null && $user->payment_customer_id === null) {
                $user->update(['payment_customer_id' => $customerId]);
            }

            return $replaced;
        });

        // The new plan is paid either way; a failed cancel is reported, not retried.
        if ($replaced !== null) {
            rescue(fn () => $this->stopPayments($replaced), report: true);
        }
    }

    /**
     * A renewal was paid, or the provider moved the paid period on. Never moves it back.
     */
    public function renew(string $subscriptionId, string $periodEnd): void
    {
        $subscription = Subscription::where('payment_subscription_id', $subscriptionId)->first();

        if ($subscription === null) {
            return;
        }

        $end = Carbon::parse($periodEnd);

        $subscription->update([
            'status' => 'active',
            'current_period_end' => $subscription->current_period_end?->gte($end) === true ? $subscription->current_period_end : $end,
        ]);
    }

    /**
     * The provider ended a subscription (cancelled, or unpaid): the account is on Free again.
     * Ignored once the account has moved on to another subscription.
     */
    public function ended(string $subscriptionId): void
    {
        Subscription::where('payment_subscription_id', $subscriptionId)->first()?->update([
            'plan_id' => Plan::free()->id,
            'billing_period' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'payment_provider' => null,
            'payment_subscription_id' => null,
            'current_period_end' => null,
        ]);
    }

    /**
     * Stops the payments behind a plan that is being replaced by one chosen without paying
     * (Free, or the free trial).
     */
    public function cancel(Subscription $subscription): void
    {
        if (! $subscription->paidByCard()) {
            return;
        }

        $this->stopPayments($subscription);
        $subscription->update(['payment_provider' => null, 'payment_subscription_id' => null, 'current_period_end' => null]);
    }

    /**
     * Cancels with the provider that takes the payments. One this server no longer talks to (an
     * older provider, or the fake driver on a server now on Paddle) has nothing to stop here.
     */
    private function stopPayments(Subscription $subscription): void
    {
        if ($subscription->payment_provider === $this->gateway->name()) {
            $this->gateway->cancelSubscription($subscription->payment_subscription_id);
        }
    }
}
