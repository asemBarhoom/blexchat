<?php

namespace App\Services\Stripe;

use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paid plans through Stripe Checkout, the hosted payment page. Prices come from the plans table
 * and go to Stripe inline, so nothing has to be set up in the Stripe dashboard. Stripe reports the
 * payment by webhook (or the success page asks); every step can run twice and changes nothing more.
 */
class StripeCheckout
{
    public function __construct(private StripeClient $stripe) {}

    /**
     * Opens a Checkout Session in subscription mode for the plan. The first period costs the
     * coupon quote's `billed_total`; renewals cost the list price.
     */
    public function start(User $user, Plan $plan, string $billing, ?Coupon $coupon = null): CheckoutSession
    {
        $currency = (string) config('services.stripe.currency');
        $months = $billing === 'annual' ? 12 : 1;
        $listCents = $plan->priceFor($billing) * 100;
        $amountCents = $listCents * $months;
        $discountCents = $coupon === null ? 0 : $coupon->discountCents($listCents) * $months;

        $customerId = $this->customerFor($user);
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $params = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $user->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'recurring' => ['interval' => $billing === 'annual' ? 'year' : 'month'],
                    'product_data' => ['name' => "Blexchat {$plan->getTranslation('name', 'en')}"],
                ],
            ]],
            'metadata' => ['user_id' => $user->id, 'plan' => $plan->slug, 'billing' => $billing],
            'subscription_data' => ['metadata' => ['user_id' => $user->id, 'plan' => $plan->slug, 'billing' => $billing]],
            // Stripe fills in {CHECKOUT_SESSION_ID}; the success page passes it to GET /checkout/session/{id}.
            'success_url' => $frontend.'/app?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend.'/app?checkout=cancelled',
        ];

        if ($discountCents > 0) {
            $stripeCoupon = $this->stripe->createCoupon([
                'amount_off' => $discountCents,
                'currency' => $currency,
                'duration' => 'once',
                'max_redemptions' => 1,
                'name' => "{$coupon->code} — {$coupon->percent_off}% off",
                'metadata' => ['coupon_id' => $coupon->id],
            ]);
            $params['discounts'] = [['coupon' => $stripeCoupon['id']]];
        }

        $session = $this->stripe->createCheckoutSession($params);

        return $user->checkoutSessions()->create([
            'plan_id' => $plan->id,
            'coupon_id' => $discountCents > 0 ? $coupon->id : null,
            'billing_period' => $billing,
            'stripe_session_id' => $session['id'],
            'amount_total' => $amountCents - $discountCents,
            'currency' => $currency,
            'url' => $session['url'],
            'status' => 'open',
        ]);
    }

    /**
     * Asks Stripe how a session ended and acts on it, for the success page when the webhook has not
     * arrived yet (or no webhook is set up on a developer's machine).
     */
    public function refresh(CheckoutSession $session): CheckoutSession
    {
        if ($session->status === 'open' && StripeClient::isConfigured()) {
            $this->handleSession($this->stripe->checkoutSession($session->stripe_session_id));
        }

        return $session->refresh();
    }

    /**
     * checkout.session.completed / checkout.session.expired, or a session fetched from Stripe.
     *
     * @param  array<string, mixed>  $stripeSession
     */
    public function handleSession(array $stripeSession): void
    {
        $session = CheckoutSession::where('stripe_session_id', $stripeSession['id'] ?? null)->first();

        if ($session === null || $session->isComplete()) {
            return;
        }

        if (($stripeSession['status'] ?? null) === 'expired') {
            $session->update(['status' => 'expired']);

            return;
        }

        $paid = ($stripeSession['status'] ?? null) === 'complete'
            && in_array($stripeSession['payment_status'] ?? null, ['paid', 'no_payment_required'], true);
        $subscriptionId = $this->id($stripeSession['subscription'] ?? null);

        if (! $paid || $subscriptionId === null) {
            return;
        }

        $this->complete($session, $subscriptionId, $this->periodEnd($subscriptionId, $session->billing_period));
    }

    /**
     * invoice.paid: a renewal moves the paid period on. The first invoice can beat
     * checkout.session.completed here; it then completes the session it came from.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function handleInvoicePaid(array $invoice): void
    {
        $subscriptionId = $this->id($invoice['subscription'] ?? Arr::get($invoice, 'parent.subscription_details.subscription'));

        if ($subscriptionId === null) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

        if ($subscription === null) {
            $session = CheckoutSession::whereNull('completed_at')
                ->whereHas('user', fn ($query) => $query->where('stripe_customer_id', $this->id($invoice['customer'] ?? null)))
                ->latest('id')
                ->first();

            if ($session !== null) {
                $this->refresh($session);
            }

            return;
        }

        $paidUntil = (int) collect(Arr::get($invoice, 'lines.data', []))->max(fn (array $line): int => (int) Arr::get($line, 'period.end', 0));
        $periodEnd = $paidUntil > 0 ? Carbon::createFromTimestamp($paidUntil) : null;

        // A replayed or late invoice never moves the period back.
        if ($periodEnd !== null && $subscription->current_period_end?->gte($periodEnd) === true) {
            $periodEnd = $subscription->current_period_end;
        }

        $subscription->update(['status' => 'active', 'current_period_end' => $periodEnd ?? $subscription->current_period_end]);
    }

    /**
     * customer.subscription.deleted: the card plan ended (cancelled, or unpaid), so the account
     * is on Free again. Ignored once the account has moved on to another subscription.
     *
     * @param  array<string, mixed>  $stripeSubscription
     */
    public function handleSubscriptionDeleted(array $stripeSubscription): void
    {
        Subscription::where('stripe_subscription_id', $stripeSubscription['id'] ?? null)->first()?->update([
            'plan_id' => Plan::free()->id,
            'billing_period' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'stripe_subscription_id' => null,
            'current_period_end' => null,
        ]);
    }

    /**
     * Stops the card payments behind a plan that is being replaced by one chosen without paying
     * (Free, or the free trial).
     */
    public function cancel(Subscription $subscription): void
    {
        if (! $subscription->paidByCard()) {
            return;
        }

        $this->stripe->cancelSubscription($subscription->stripe_subscription_id);
        $subscription->update(['stripe_subscription_id' => null, 'current_period_end' => null]);
    }

    /**
     * Puts the paid plan in place and counts the coupon, once even when Stripe and the success page
     * report the payment at the same moment. A card plan it replaces is cancelled at Stripe.
     */
    private function complete(CheckoutSession $session, string $subscriptionId, Carbon $periodEnd): void
    {
        $replaced = DB::transaction(function () use ($session, $subscriptionId, $periodEnd): ?string {
            $session = CheckoutSession::whereKey($session->id)->lockForUpdate()->first();

            if ($session->isComplete()) {
                return null;
            }

            $current = $session->user->subscription;
            $replaced = $current?->stripe_subscription_id !== $subscriptionId ? $current?->stripe_subscription_id : null;

            $session->user->subscription()->updateOrCreate([], [
                'plan_id' => $session->plan_id,
                'billing_period' => $session->billing_period,
                'status' => 'active',
                'trial_ends_at' => null,
                'stripe_subscription_id' => $subscriptionId,
                'current_period_end' => $periodEnd,
            ]);
            $session->update(['status' => 'complete', 'stripe_subscription_id' => $subscriptionId, 'completed_at' => now()]);
            $session->coupon?->redeem();

            return $replaced;
        });

        // The new plan is paid either way; a failed cancel is reported, not retried.
        if ($replaced !== null) {
            rescue(fn () => $this->stripe->cancelSubscription($replaced), report: true);
        }
    }

    private function customerFor(User $user): string
    {
        if ($user->stripe_customer_id === null) {
            $customer = $this->stripe->createCustomer([
                'email' => $user->email,
                'name' => $user->business_name ?? $user->name,
                'metadata' => ['user_id' => $user->id],
            ]);
            $user->update(['stripe_customer_id' => $customer['id']]);
        }

        return $user->stripe_customer_id;
    }

    /**
     * When the first paid period ends. Newer Stripe API versions keep it on the subscription items.
     */
    private function periodEnd(string $subscriptionId, string $billing): Carbon
    {
        try {
            $subscription = $this->stripe->subscription($subscriptionId);
            $end = $subscription['current_period_end'] ?? Arr::get($subscription, 'items.data.0.current_period_end');
        } catch (RequestException|ConnectionException) {
            $end = null;
        }

        return $end !== null ? Carbon::createFromTimestamp($end) : ($billing === 'annual' ? now()->addYear() : now()->addMonth());
    }

    /**
     * Stripe sends a related object as its id, or as the object when it was expanded.
     */
    private function id(mixed $value): ?string
    {
        return is_array($value) ? ($value['id'] ?? null) : (is_string($value) && $value !== '' ? $value : null);
    }
}
