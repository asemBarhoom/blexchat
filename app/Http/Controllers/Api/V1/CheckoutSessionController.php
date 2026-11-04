<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Checkout\CreateCheckoutSessionRequest;
use App\Http\Resources\Api\V1\CheckoutSessionResource;
use App\Models\Coupon;
use App\Models\Plan;
use App\Services\Stripe\StripeCheckout;
use App\Services\Stripe\StripeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CheckoutSession', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'cs_test_a1B2c3', description: 'The Stripe Checkout Session id; the success page gets it back as `session_id`'),
    new OA\Property(property: 'url', type: 'string', example: 'https://checkout.stripe.com/c/pay/cs_test_a1B2c3', description: 'Send the browser here: the Stripe-hosted payment page'),
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
    public function __construct(private StripeCheckout $checkout) {}

    #[OA\Post(
        path: '/api/v1/checkout/session',
        summary: 'Pay for a plan by card (Stripe Checkout)',
        description: <<<'MD'
            "Continue To Payment" in the checkout. Opens a Stripe Checkout Session and returns its `url`: send the
            browser there. After paying, Stripe sends the visitor to `FRONTEND_URL/app?checkout=success&session_id=…`
            (call GET /api/v1/checkout/session/{id} there); "Back" on the payment page returns to
            `FRONTEND_URL/app?checkout=cancelled` with nothing charged.

            The plan is in place once Stripe reports the payment. It renews every month or year until cancelled.
            A `coupon` (checked with POST /api/v1/coupons/check) comes off the first payment.

            Free trials never come here: they take no card (POST /api/v1/onboarding/plan).
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
            new OA\Response(response: 503, description: '`payments_unavailable`: no Stripe key is set on this server', content: new OA\JsonContent(ref: '#/components/schemas/CheckoutError')),
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

        return self::startCheckout($this->checkout, $request, $plan, $request->billing(), $coupon, 201);
    }

    #[OA\Get(
        path: '/api/v1/checkout/session/{id}',
        summary: 'How a card payment ended',
        description: 'For the success page: `session_id` from its address. While Stripe has not reported the payment yet, this asks Stripe, so the plan shows as soon as it is paid. Poll every few seconds while `status` is `open`.',
        security: [['sanctum' => []]],
        tags: ['Checkout'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cs_test_a1B2c3'),
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

    /**
     * Opens the Stripe payment page, shared with POST /onboarding/plan for a plan that has to be paid.
     */
    public static function startCheckout(StripeCheckout $checkout, Request $request, Plan $plan, string $billing, ?Coupon $coupon, int $status): JsonResponse
    {
        if (! StripeClient::isConfigured()) {
            return self::error(503, 'payments_unavailable');
        }

        try {
            $session = $checkout->start($request->user(), $plan, $billing, $coupon);
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            return self::error(502, 'payment_provider_error');
        }

        return (new CheckoutSessionResource($session->load(['plan', 'coupon'])))->response()->setStatusCode($status);
    }

    public static function error(int $status, string $code): JsonResponse
    {
        return response()->json(['message' => __("checkout.{$code}"), 'code' => $code], $status);
    }
}
