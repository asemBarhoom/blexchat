<?php

namespace App\Services\Subscriptions;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentCheckout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Puts an owner on a plan, from the onboarding plan screen (W09) or later from the app.
 *
 * Free applies at once. A plan with a trial starts it, once per account, and takes no card.
 * Any other paid plan (and the trial plan after the account's trial) opens a payment page (Stripe,
 * or the fake one in development) and changes once it is paid; with no payments available it is
 * refused. Moving off a paid plan to Free or a trial stops its payments.
 */
class PlanChanger
{
    public function __construct(private PaymentCheckout $checkout) {}

    /**
     * The plan in place, or the payment page to send the owner to.
     *
     * @throws PlanChangeRefused
     */
    public function change(User $user, Plan $plan, string $billing): Subscription|CheckoutSession
    {
        $current = $user->subscription()->first();

        if (! $plan->isFree() && ! $user->canStartTrialOn($plan)) {
            if ($current?->paidByCard() && $current->plan_id === $plan->id && $current->billing_period === $billing) {
                return $current->load('plan');
            }

            if (! $this->checkout->isAvailable()) {
                throw $plan->hasTrial() ? new PlanChangeRefused('trial_used', 409) : new PlanChangeRefused('payment_required', 402);
            }

            return $this->withProvider(fn (): CheckoutSession => $this->checkout->start($user, $plan, $billing)->load(['plan', 'coupon']));
        }

        if ($current?->paidByCard()) {
            $this->withProvider(fn () => $this->checkout->cancel($current));
        }

        // Picking the plan you are already trying keeps the trial's clock as it is.
        $keepsTrial = $current?->onTrial() === true && $current->plan_id === $plan->id;

        $state = match (true) {
            $keepsTrial => [],
            $plan->hasTrial() => ['status' => 'trialing', 'trial_ends_at' => now()->addDays($plan->trial_days), 'trial_used_at' => now()],
            default => ['status' => 'active', 'trial_ends_at' => null],
        };

        $subscription = $user->subscription()->updateOrCreate([], [
            'plan_id' => $plan->id,
            'billing_period' => $billing,
            ...$state,
        ]);

        return $subscription->load('plan');
    }

    /**
     * Back to Free at once, ending any trial and any card payments. The trial stays used.
     *
     * @throws PlanChangeRefused
     */
    public function cancel(User $user): Subscription
    {
        $billing = $user->subscription()->value('billing_period') ?? 'monthly';

        return $this->change($user, Plan::free(), $billing);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws PlanChangeRefused when the payment provider cannot be reached or refuses
     */
    private function withProvider(callable $call): mixed
    {
        try {
            return $call();
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            throw new PlanChangeRefused('payment_provider_error', 502);
        }
    }
}
