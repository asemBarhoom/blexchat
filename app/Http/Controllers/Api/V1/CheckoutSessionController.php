<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Checkout\CreateCheckoutSessionRequest;
use App\Http\Resources\Api\V1\CheckoutSessionResource;
use App\Models\Coupon;
use App\Models\Plan;
use App\Services\Payments\PaymentCheckout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CheckoutSession', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'fake_cs_Kq3vX9mT2pLw8RbN4cYh7eFz', description: 'The payment session id (`cs_…` on Stripe, `fake_cs_…` on the fake driver); the success page gets it back as `session_id`'),
    new OA\Property(property: 'url', type: 'string', example: 'https://blexchat-production-ewv0u6.laravel.cloud/payments/fake/fake_cs_Kq3vX9mT2pLw8RbN4cYh7eFz?expires=1793900000&signature=…', description: 'Send the browser here: the fake test page on development servers, or the Stripe-hosted page in production'),
    new OA\Property(property: 'status', type: 'string', enum: ['open', 'complete', 'expired'], description: '`complete` once paid: `subscription` then holds the plan in place'),
    new OA\Property(property: 'plan', type: 'string', example: 'pro'),
    new OA\Property(property: 'billing', type: 'string', enum: ['monthly', 'annual']),
    new OA\Property(property: 'coupon', type: 'string', nullable: true, example: 'BLEX20'),
    new OA\Property(property: 'amount_total', type: 'number', example: 31.2, description: 'What the first payment charges, after the coupon: twelve months when billed yearly. Renewals charge the list price'),
    new OA\Property(property: 'currency', type: 'string', example: 'usd'),
    new OA\Property(property: 'subscription', ref: '#/components/schemas/Subscription', description: 'Only when `status` is `complete`'),
])]
#[OA\Schema(schema: 'CheckoutError', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', description: 'Ready to show, in the requested language'),
    new OA\Property(property: 'code', type: 'string', enum: ['plan_already_paid', 'payments_unavailable', 'payment_provider_error'], description: 'Stable value to branch on'),
])]
class CheckoutSessionController extends Controller
{
    public function __construct(private PaymentCheckout $checkout) {}

    #[OA\Post(
        path: '/api/v1/checkout/session',
        summary: 'Open the payment page for a plan ("Continue To Payment")',
        description: <<<'MD'
            Returns a payment session; send the browser to its `url`. Which page that is depends on the server's
            `PAYMENT_DRIVER`, and the frontend handles both the same way.

            **Fake driver (`PAYMENT_DRIVER=fake`: the development server and local).** `url` is a test page on this
            API (`/payments/fake/{id}?expires=…&signature=…`) showing the plan, the billing period, the coupon and
            the total, with two buttons and no card:

            | Button | Effect | Browser goes to |
            |---|---|---|
            | Pay (test) | The plan is paid and in place at once | `FRONTEND_URL/app?checkout=success&session_id={id}` |
            | Decline | Nothing is charged; the session stays `open` | `FRONTEND_URL/app?checkout=cancelled` |

            Pay on a session that has expired also goes to `checkout=cancelled`. Add `&lang=ar` to `url` for the
            Arabic page. The page answers 403 when its link is altered or older than 24 hours, and 404 wherever
            the fake driver is off.

            **Stripe (`PAYMENT_DRIVER=stripe`: production).** `url` is the Stripe-hosted page; it sends the browser
            to the same two addresses. The plan is in place once Stripe reports the payment, a few seconds later.

            **On `checkout=success`:** read `session_id` from the address and call
            GET /api/v1/checkout/session/{session_id}. `status: complete` means paid, and `subscription` holds the
            plan; poll every few seconds while it is `open`. **On `checkout=cancelled`:** show the plan unchanged;
            the visitor may start again.

            A `coupon` (checked with POST /api/v1/coupons/check) comes off the first payment. The plan renews every
            month or year until cancelled. Free trials never come here: they take no card (POST /api/v1/onboarding/plan).
            MD,
        security: [['sanctum' => []]],
        tags: ['Checkout'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the error messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['plan'], properties: [
            new OA\Property(property: 'plan', type: 'string', example: 'pro', description: 'A paid plan `id` from GET /api/v1/plans'),
            new OA\Property(property: 'billing', type: 'string', enum: ['monthly', 'annual'], default: 'monthly'),
            new OA\Property(property: 'coupon', type: 'string', nullable: true, example: 'BLEX20'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'The payment page is ready', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CheckoutSession'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 409, description: '`plan_already_paid`: this plan and billing period are already paid by card', content: new OA\JsonContent(ref: '#/components/schemas/CheckoutError')),
            new OA\Response(response: 422, description: 'A coupon that does not apply (CouponError), or an unknown or free plan or billing period (ValidationError)', content: new OA\JsonContent(ref: '#/components/schemas/CouponError')),
            new OA\Response(response: 429, description: 'Too many attempts; try again in a minute'),
            new OA\Response(response: 502, description: '`payment_provider_error`: Stripe could not be reached', content: new OA\JsonContent(ref: '#/components/schemas/CheckoutError')),
            new OA\Response(response: 503, description: '`payments_unavailable`: no Stripe key is set on this server (or the fake driver is refused in production)', content: new OA\JsonContent(ref: '#/components/schemas/CheckoutError')),
        ],
    )]
    public function store(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $plan = $request->plan();
        $current = $request->user()->subscription()->first();

        if ($current?->paidByCard() && $current->plan_id === $plan->id && $current->billing_period === $request->billing()) {
            return self::error(409, 'plan_already_paid');
        }

        $coupon = null;

        if ($request->filled('coupon')) {
            $coupon = Coupon::findByCode($request->string('coupon'));
            $rejection = $coupon === null ? Coupon::INVALID : $coupon->rejectionFor($plan, onTrial: false);

            if ($rejection !== null) {
                $message = $rejection === Coupon::EXPIRED && ! $coupon->isExpired() ? 'coupon_used_up' : $rejection;

                return response()->json(['message' => __("checkout.{$message}", ['plan' => $plan->name]), 'code' => $rejection], 422);
            }
        }

        return $this->startCheckout($request, $plan, $coupon);
    }

    #[OA\Get(
        path: '/api/v1/checkout/session/{id}',
        summary: 'How a card payment ended',
        description: 'For `FRONTEND_URL/app?checkout=success&session_id={id}`: pass `session_id` from that address. `complete` means paid and `subscription` holds the plan. On the fake driver it is `complete` straight after "Pay (test)"; on Stripe it may still be `open` for a few seconds (this asks Stripe), so poll every few seconds while `status` is `open`. After "Decline" it stays `open`.',
        security: [['sanctum' => []]],
        tags: ['Checkout'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'fake_cs_Kq3vX9mT2pLw8RbN4cYh7eFz'),
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the plan texts. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The session', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CheckoutSession'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 404, description: 'No such session for this account'),
        ],
    )]
    public function show(Request $request, string $id): CheckoutSessionResource
    {
        $session = $request->user()->checkoutSessions()->where('stripe_session_id', $id)->firstOrFail();

        // Stripe being unreachable only means the answer is "not yet".
        rescue(fn () => $this->checkout->refresh($session), report: true);

        return new CheckoutSessionResource($session->load(['plan', 'coupon']));
    }

    private function startCheckout(CreateCheckoutSessionRequest $request, Plan $plan, ?Coupon $coupon): JsonResponse
    {
        if (! $this->checkout->isAvailable()) {
            return self::error(503, 'payments_unavailable');
        }

        try {
            $session = $this->checkout->start($request->user(), $plan, $request->billing(), $coupon);
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            return self::error(502, 'payment_provider_error');
        }

        return (new CheckoutSessionResource($session->load(['plan', 'coupon'])))->response()->setStatusCode(201);
    }

    private static function error(int $status, string $code): JsonResponse
    {
        return response()->json(['message' => __("checkout.{$code}"), 'code' => $code], $status);
    }
}
