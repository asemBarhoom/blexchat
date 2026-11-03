<?php

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Puts an owner on a plan, from the onboarding plan screen (W09) or later from the app.
 *
 * Free applies at once. A plan with a trial starts it, once per account, and takes no card.
 * Any other paid plan is refused until card payments exist.
 */
class PlanChanger
{
    /**
     * @throws PlanChangeRefused
     */
    public function change(User $user, Plan $plan, string $billing): Subscription
    {
        $current = $user->subscription()->first();

        if (! $plan->isFree() && ! $plan->hasTrial()) {
            throw new PlanChangeRefused('payment_required', 402);
        }

        // Picking the plan you are already trying keeps the trial's clock as it is.
        $keepsTrial = $current?->onTrial() === true && $current->plan_id === $plan->id;

        if ($plan->hasTrial() && ! $keepsTrial && $current?->trial_used_at !== null) {
            throw new PlanChangeRefused('trial_used', 409);
        }

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
     * Back to Free at once, ending any trial. The trial stays used.
     */
    public function cancel(User $user): Subscription
    {
        $billing = $user->subscription()->value('billing_period') ?? 'monthly';

        return $this->change($user, Plan::free(), $billing);
    }
}
