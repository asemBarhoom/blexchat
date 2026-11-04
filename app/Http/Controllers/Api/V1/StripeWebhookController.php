<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentCheckout;
use App\Services\Stripe\StripeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StripeWebhookController extends Controller
{
    #[OA\Post(
        path: '/api/v1/webhooks/stripe',
        summary: 'Receive Stripe payment events',
        description: <<<'MD'
            Stripe calls this; requests are authenticated by the `Stripe-Signature` header (HMAC-SHA256 with
            STRIPE_WEBHOOK_SECRET, at most 5 minutes old), not a user. Events handled:

            - `checkout.session.completed`: the plan paid for is put in place.
            - `invoice.paid`: a renewal; the paid period moves on.
            - `customer.subscription.deleted`: the card plan ended; the account is on Free again.
            - `checkout.session.expired`: the payment page closed unpaid.

            Stripe may send an event more than once; handling it again changes nothing. Other events are acknowledged and ignored.
            MD,
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Accepted'),
            new OA\Response(response: 400, description: 'Missing, invalid or stale signature'),
        ]
    )]
    public function __invoke(Request $request, PaymentCheckout $checkout): JsonResponse
    {
        $event = StripeClient::verifiedEvent($request->getContent(), (string) $request->header('Stripe-Signature'));

        if ($event === null) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $object = $event['data']['object'] ?? [];

        match ($event['type'] ?? null) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired' => $checkout->handleSession($object),
            'invoice.paid' => $checkout->handleInvoicePaid($object),
            'customer.subscription.deleted' => $checkout->handleSubscriptionDeleted($object),
            default => null,
        };

        return response()->json(['status' => 'received']);
    }
}
