<?php

namespace App\Services\Stripe;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The few Stripe REST calls card payments need, with Laravel's HTTP client (no Stripe SDK).
 * Stripe takes form-encoded bodies, nested with brackets: line_items[0][price_data][currency].
 */
class StripeClient
{
    private const API = 'https://api.stripe.com/v1';

    public static function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * Launch readiness is stricter than checkout availability: production needs live charges,
     * signed renewal webhooks and a valid three-letter Stripe currency.
     */
    public static function isReadyForProduction(): bool
    {
        return config('services.payments.driver') === 'stripe'
            && ! config('services.payments.allow_fake')
            && str_starts_with((string) config('services.stripe.secret'), 'sk_live_')
            && str_starts_with((string) config('services.stripe.webhook_secret'), 'whsec_')
            && preg_match('/^[a-z]{3}$/', (string) config('services.stripe.currency')) === 1;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCustomer(array $params): array
    {
        return $this->request()->post(self::API.'/customers', $params)->throw()->json();
    }

    /**
     * A one-off coupon for one checkout: Checkout Sessions take discounts by coupon id only.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCoupon(array $params): array
    {
        return $this->request()->post(self::API.'/coupons', $params)->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string, status: string, amount_total: int, currency: string}
     */
    public function createCheckoutSession(array $params): array
    {
        return $this->request()->post(self::API.'/checkout/sessions', $params)->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutSession(string $id): array
    {
        return $this->request()->get(self::API.'/checkout/sessions/'.urlencode($id))->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function subscription(string $id): array
    {
        return $this->request()->get(self::API.'/subscriptions/'.urlencode($id))->throw()->json();
    }

    /**
     * Stops a Stripe subscription at once: no further charges.
     */
    public function cancelSubscription(string $id): void
    {
        $response = $this->request()->delete(self::API.'/subscriptions/'.urlencode($id));

        // Already cancelled, or gone: there is nothing left to stop.
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * The event in a webhook body, or null when the Stripe-Signature header does not prove that
     * Stripe sent it within the tolerance. The header reads `t=1700000000,v1=<hex>[,v1=<hex>]`,
     * and each v1 is HMAC-SHA256 of "{t}.{body}" with the endpoint's signing secret.
     *
     * @return array<string, mixed>|null
     */
    public static function verifiedEvent(string $payload, string $header): ?array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($secret === '' || $timestamp === null || abs(time() - $timestamp) > (int) config('services.stripe.webhook_tolerance')) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $signed = collect($signatures)->contains(fn (string $signature): bool => hash_equals($expected, $signature));
        $event = json_decode($payload, true);

        return $signed && is_array($event) ? $event : null;
    }

    /**
     * Signs a body the way Stripe does, for tests and local tools.
     */
    public static function signature(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$payload, (string) config('services.stripe.webhook_secret'));
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
    }
}
