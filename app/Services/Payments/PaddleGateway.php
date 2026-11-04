<?php

namespace App\Services\Payments;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Paddle Billing over its REST API, with Laravel's HTTP client (no SDK). A checkout is a transaction
 * with a non-catalog price built from the plans table, so nothing has to be set up in the Paddle
 * catalog; Paddle answers with `checkout.url`: the default payment link (GET /payments/paddle here,
 * which loads Paddle.js) plus `?_ptxn=<transaction id>`.
 */
class PaddleGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paddle';
    }

    public function isAvailable(): bool
    {
        return filled(config('services.paddle.api_key'));
    }

    public static function isSandbox(): bool
    {
        return config('services.paddle.environment') !== 'production';
    }

    public function createCheckout(User $user, Plan $plan, string $billing, int $amountCents, ?Coupon $coupon, int $discountCents): array
    {
        $currency = strtoupper((string) config('services.payments.currency'));
        $name = $plan->getTranslation('name', 'en');
        $period = $billing === 'annual' ? 'annual' : 'monthly';

        $body = [
            'items' => [[
                'quantity' => 1,
                'price' => [
                    'description' => "Blexchat {$name}, billed {$period}",
                    'name' => "{$name} ({$period})",
                    'billing_cycle' => ['interval' => $billing === 'annual' ? 'year' : 'month', 'frequency' => 1],
                    'unit_price' => ['amount' => (string) $amountCents, 'currency_code' => $currency],
                    'product' => ['name' => "Blexchat {$name}", 'tax_category' => 'standard'],
                ],
            ]],
            'currency_code' => $currency,
            'custom_data' => ['user_id' => (string) $user->id, 'plan' => $plan->slug, 'billing' => $billing],
        ];

        // Paddle's checkout collects the email and address; a returning customer is prefilled.
        if ($user->payment_customer_id !== null && str_starts_with($user->payment_customer_id, 'ctm_')) {
            $body['customer_id'] = $user->payment_customer_id;
        }

        if ($coupon !== null && $discountCents > 0) {
            // A non-catalog flat discount off the first payment only (`recur` false).
            $body['discount'] = [
                'type' => 'flat',
                'amount' => (string) $discountCents,
                'description' => "{$coupon->code} — {$coupon->percent_off}% off",
                'recur' => false,
            ];
        }

        $transaction = $this->send(fn (PendingRequest $paddle): Response => $paddle->post('/transactions', $body))->json('data');
        $url = $transaction['checkout']['url'] ?? null;

        // No URL means no default payment link is set in the Paddle dashboard.
        throw_if(blank($url), PaymentProviderException::class, 'Paddle returned no checkout URL: set the default payment link (Checkout > Checkout settings).');

        return ['id' => $transaction['id'], 'url' => $url];
    }

    /**
     * A transaction is paid once `paid` (captured) or `completed` (processed).
     */
    public function findCheckout(string $id): ?array
    {
        $transaction = $this->send(fn (PendingRequest $paddle): Response => $paddle->get('/transactions/'.urlencode($id)))->json('data');

        return [
            'paid' => in_array($transaction['status'] ?? null, ['paid', 'completed'], true),
            'subscription_id' => $transaction['subscription_id'] ?? null,
            'customer_id' => $transaction['customer_id'] ?? null,
            'period_end' => $transaction['billing_period']['ends_at'] ?? null,
        ];
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        // Gone already (404): there is nothing left to stop.
        $this->send(fn (PendingRequest $paddle): Response => $paddle->post('/subscriptions/'.urlencode($subscriptionId).'/cancel', ['effective_from' => 'immediately']), allow: [404]);
    }

    /**
     * The event in a webhook body, or null when the Paddle-Signature header does not prove that
     * Paddle sent it within the tolerance. The header reads `ts=1671552777;h1=<hex>[;h1=<hex>]`, and
     * each h1 is HMAC-SHA256 of "{ts}:{raw body}" with the notification destination's secret key.
     *
     * @return array<string, mixed>|null
     */
    public static function verifiedEvent(string $payload, string $header): ?array
    {
        $secret = (string) config('services.paddle.webhook_secret');
        $timestamp = null;
        $signatures = [];

        foreach (explode(';', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 'ts' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'h1') {
                $signatures[] = $value;
            }
        }

        if ($secret === '' || $timestamp === null || abs(time() - $timestamp) > (int) config('services.paddle.webhook_tolerance')) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$payload, $secret);
        $signed = collect($signatures)->contains(fn (string $signature): bool => hash_equals($expected, $signature));
        $event = json_decode($payload, true);

        return $signed && is_array($event) ? $event : null;
    }

    /**
     * Signs a body the way Paddle does, for tests and local tools.
     */
    public static function signature(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "ts={$timestamp};h1=".hash_hmac('sha256', $timestamp.':'.$payload, (string) config('services.paddle.webhook_secret'));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @param  list<int>  $allow  Error statuses that are not a failure
     *
     * @throws PaymentProviderException
     */
    private function send(callable $call, array $allow = []): Response
    {
        try {
            $response = $call($this->request());

            return in_array($response->status(), $allow, true) ? $response : $response->throw();
        } catch (RequestException|ConnectionException $exception) {
            throw new PaymentProviderException('Paddle: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::isSandbox() ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com')
            ->withToken((string) config('services.paddle.api_key'))
            ->withHeaders(['Paddle-Version' => '1'])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);
    }
}
