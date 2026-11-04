<?php

namespace App\Services\Stripe;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

/**
 * Stripe Checkout, the hosted payment page. Prices go to Stripe inline (price_data), so nothing has
 * to be set up in the Stripe dashboard; a coupon becomes a one-off Stripe coupon off the first payment.
 */
class StripeGateway implements PaymentGateway
{
    public function __construct(private StripeClient $stripe) {}

    public function isAvailable(): bool
    {
        return StripeClient::isConfigured();
    }

    public function createCheckout(User $user, Plan $plan, string $billing, int $amountCents, ?Coupon $coupon, int $discountCents, string $successUrl, string $cancelUrl): array
    {
        $currency = (string) config('services.stripe.currency');

        $params = [
            'mode' => 'subscription',
            'customer' => $this->customerFor($user),
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
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        if ($coupon !== null && $discountCents > 0) {
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

        return ['id' => $session['id'], 'url' => $session['url']];
    }

    public function findCheckout(string $id): ?array
    {
        return $this->stripe->checkoutSession($id);
    }

    /**
     * Newer Stripe API versions keep the period on the subscription items.
     */
    public function periodEnd(string $subscriptionId): ?int
    {
        try {
            $subscription = $this->stripe->subscription($subscriptionId);
        } catch (RequestException|ConnectionException) {
            return null;
        }

        return $subscription['current_period_end'] ?? Arr::get($subscription, 'items.data.0.current_period_end');
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->stripe->cancelSubscription($subscriptionId);
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
}
