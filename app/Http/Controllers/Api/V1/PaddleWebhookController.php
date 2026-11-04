<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Services\Payments\PaddleGateway;
use App\Services\Payments\PaymentCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use OpenApi\Attributes as OA;

class PaddleWebhookController extends Controller
{
    #[OA\Post(
        path: '/api/v1/webhooks/paddle',
        summary: 'Receive Paddle Billing events',
        description: <<<'MD'
            Paddle calls this (a notification destination); requests are authenticated by the `Paddle-Signature`
            header (HMAC-SHA256 of `ts:body` with PADDLE_WEBHOOK_SECRET, at most 5 seconds old), not a user. Events handled:

            - `transaction.completed`: the first payment puts the plan in place; a renewal moves the paid period on.
            - `subscription.created`: the plan is in place (whichever of this and `transaction.completed` comes first).
            - `subscription.updated`: the paid period moves on; a `canceled` status ends the plan.
            - `subscription.canceled`: the plan ended; the account is on Free again.

            Paddle may send an event more than once; handling it again changes nothing. Other events are acknowledged and ignored.
            MD,
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
            new OA\Response(response: 400, description: 'Missing, invalid or stale signature'),
        ]
    )]
    public function __invoke(Request $request, PaymentCheckout $checkout): JsonResponse
    {
        $event = PaddleGateway::verifiedEvent($request->getContent(), (string) $request->header('Paddle-Signature'));

        if ($event === null) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $data = $event['data'] ?? [];

        match ($event['event_type'] ?? null) {
            'transaction.completed' => $this->transactionCompleted($checkout, $data),
            'subscription.created' => $this->subscriptionCreated($checkout, $data),
            'subscription.updated' => $this->subscriptionUpdated($checkout, $data),
            'subscription.canceled' => $checkout->ended((string) ($data['id'] ?? '')),
            default => null,
        };

        return response()->json(['status' => 'received']);
    }

    /**
     * The checkout's own transaction completes it; a later one (a renewal) renews the subscription.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function transactionCompleted(PaymentCheckout $checkout, array $transaction): void
    {
        $subscriptionId = $transaction['subscription_id'] ?? null;
        $periodEnd = Arr::get($transaction, 'billing_period.ends_at');

        if ($subscriptionId === null) {
            return;
        }

        $session = $this->session($transaction['id'] ?? null);

        if ($session !== null && ! $session->isComplete()) {
            $checkout->markPaid($session, $subscriptionId, $periodEnd, $transaction['customer_id'] ?? null);
        } elseif ($periodEnd !== null) {
            $checkout->renew($subscriptionId, $periodEnd);
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function subscriptionCreated(PaymentCheckout $checkout, array $subscription): void
    {
        $session = $this->session($subscription['transaction_id'] ?? null);

        if ($session !== null && isset($subscription['id'])) {
            $checkout->markPaid($session, $subscription['id'], Arr::get($subscription, 'current_billing_period.ends_at'), $subscription['customer_id'] ?? null);
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function subscriptionUpdated(PaymentCheckout $checkout, array $subscription): void
    {
        $id = (string) ($subscription['id'] ?? '');
        $periodEnd = Arr::get($subscription, 'current_billing_period.ends_at');

        if (($subscription['status'] ?? null) === 'canceled') {
            $checkout->ended($id);
        } elseif ($periodEnd !== null) {
            $checkout->renew($id, $periodEnd);
        }
    }

    private function session(?string $transactionId): ?CheckoutSession
    {
        return $transactionId === null ? null : CheckoutSession::where('payment_provider', 'paddle')->where('payment_session_id', $transactionId)->first();
    }
}
