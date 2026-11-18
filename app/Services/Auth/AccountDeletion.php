<?php

namespace App\Services\Auth;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an account and everything it owns. Workspaces, channels, contacts, conversations and
 * automations go with the user through the database's cascades; teammates keep their own sign-in
 * and simply leave the team.
 */
class AccountDeletion
{
    public function __construct(private PaymentGateway $gateway) {}

    public function delete(User $user): void
    {
        $stripeSubscriptionId = $user->subscription?->stripe_subscription_id;

        if ($stripeSubscriptionId !== null) {
            // A card plan must stop charging even if the rest fails; a provider error is reported, not fatal.
            rescue(fn () => $this->gateway->cancelSubscription($stripeSubscriptionId), report: true);
        }

        DB::transaction(function () use ($user): void {
            if ($user->isTeammate()) {
                Conversation::where('assigned_to', $user->id)->update(['assigned_to' => null]);
            }

            $user->teammates()->each(fn (User $teammate): bool => $teammate->forceFill(['team_owner_id' => null, 'workspace_id' => null])->save());
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
