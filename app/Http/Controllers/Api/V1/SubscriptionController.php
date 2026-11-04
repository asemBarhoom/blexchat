<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\ChoosePlanRequest;
use App\Http\Requests\Api\Subscription\ChangePlanRequest;
use App\Http\Resources\Api\V1\CheckoutSessionResource;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\CheckoutSession;
use App\Models\Subscription;
use App\Services\Subscriptions\PlanChanger;
use App\Services\Subscriptions\PlanChangeRefused;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Subscription', properties: [
    new OA\Property(property: 'plan', ref: '#/components/schemas/Plan', description: 'The tier in force today: Free once a trial has ended'),
    new OA\Property(property: 'chosen_plan', type: 'string', example: 'essential', description: 'The plan picked, even after its trial ended'),
    new OA\Property(property: 'billing_period', type: 'string', enum: ['monthly', 'annual']),
    new OA\Property(property: 'status', type: 'string', enum: ['active', 'trialing', 'trial_ended'], description: 'trial_ended: the trial ran out and was not bought (S6.4), so `plan` is Free'),
    new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'trial_days_left', type: 'integer', nullable: true, example: 14, description: 'While on a trial only'),
    new OA\Property(property: 'paid_by_card', type: 'boolean', example: false, description: 'A Paddle subscription (or the fake driver) pays for the plan and renews it'),
    new OA\Property(property: 'current_period_end', type: 'string', format: 'date-time', nullable: true, description: 'When the paid period renews; card plans only'),
])]
#[OA\Schema(schema: 'PlanError', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', description: 'Ready to show, in the requested language'),
    new OA\Property(property: 'code', type: 'string', enum: ['payment_required', 'trial_used', 'payment_provider_error'], description: 'Stable value to branch on'),
])]
#[OA\Tag(name: 'Subscription', description: 'The plan after onboarding: read it, change it, cancel back to Free')]
class SubscriptionController extends Controller
{
    public function __construct(private PlanChanger $planChanger) {}

    #[OA\Post(
        path: '/api/v1/onboarding/plan',
        summary: 'Choose a plan (W09)',
        description: <<<'MD'
            - **Free** ("Continue with Free"): active at once.
            - **A plan with `trial_days`** ("Start free trial" in the checkout): a trial starts and no card is taken. It does not convert: when it ends the account is on Free again. One trial per account.
            - **Any other paid plan**, and the trial plan once the account has had its trial: 202 with a payment page
              (`data.url`, as POST /api/v1/checkout/session). The plan changes once it is paid. Without a Paddle API key on the
              server: 402 `payment_required` (409 `trial_used` for the trial plan) instead.

            Choosing again replaces the current plan. Choosing Free or a trial stops the card payments of a plan paid by card.
            MD,
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts and error messages in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['plan'], properties: [
            new OA\Property(property: 'plan', type: 'string', example: 'essential', description: 'A plan `id` from GET /api/v1/plans'),
            new OA\Property(property: 'billing', type: 'string', enum: ['monthly', 'annual'], default: 'monthly', description: 'The Monthly / Annually switch'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'The plan is in place', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
            ])),
            new OA\Response(response: 202, description: 'A paid plan: send the browser to `data.url` to pay', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CheckoutSession'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 402, description: '`payment_required`: a paid plan, and no Paddle API key on this server', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 409, description: '`trial_used`: this account already had its free trial, and no Paddle API key on this server', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 422, description: 'Unknown plan or billing period', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 502, description: '`payment_provider_error`: Paddle could not be reached', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
        ],
    )]
    public function store(ChoosePlanRequest $request): JsonResponse
    {
        try {
            $subscription = $this->planChanger->change($request->user(), $request->plan(), $request->billing());
        } catch (PlanChangeRefused $refused) {
            return $refused->toResponse('onboarding.plan');
        }

        return self::changed($subscription);
    }

    #[OA\Get(
        path: '/api/v1/subscription',
        summary: 'The current plan',
        description: 'The same `subscription` as GET /api/v1/onboarding. `data` is null before a plan is chosen (W09); the account is then on Free.',
        security: [['sanctum' => []]],
        tags: ['Subscription'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'The subscription, or null', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Subscription', nullable: true),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription()->with('plan')->first();

        return $subscription
            ? (new SubscriptionResource($subscription))->response()
            : response()->json(['data' => null]);
    }

    #[OA\Post(
        path: '/api/v1/subscription',
        summary: 'Change the plan or the billing period',
        description: <<<'MD'
            The same rules as POST /api/v1/onboarding/plan:
            - **Free**: active at once, ending any trial.
            - **A plan with `trial_days`**: its trial starts, once per account (409 `trial_used` after). Picking the plan being tried again keeps the trial's clock.
            - **Any other paid plan**, and the trial plan after the trial: 202 with a payment page (`data.url`); the plan
              changes once it is paid. Without a Paddle API key on the server: 402 `payment_required` (409 `trial_used`).
            - Moving off a plan paid by card to Free or a trial stops its card payments.

            Send `plan`, `billing` or both; what is left out stays as it is. `billing` alone needs a plan chosen already.
            MD,
        security: [['sanctum' => []]],
        tags: ['Subscription'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts and error messages in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'plan', type: 'string', example: 'essential', description: 'A plan `id` from GET /api/v1/plans. Required without `billing`.'),
            new OA\Property(property: 'billing', type: 'string', enum: ['monthly', 'annual'], description: 'Required without `plan`. Defaults to the current billing period.'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'The plan is in place', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
            ])),
            new OA\Response(response: 202, description: 'A paid plan: send the browser to `data.url` to pay', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CheckoutSession'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 402, description: '`payment_required`: a paid plan, and no Paddle API key on this server', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 409, description: '`trial_used`: this account already had its free trial, and no Paddle API key on this server', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 422, description: 'Unknown plan or billing period, or neither sent', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 502, description: '`payment_provider_error`: Paddle could not be reached', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
        ],
    )]
    public function update(ChangePlanRequest $request): JsonResponse
    {
        try {
            $subscription = $this->planChanger->change($request->user(), $request->plan(), $request->billing());
        } catch (PlanChangeRefused $refused) {
            return $refused->toResponse('subscription');
        }

        return self::changed($subscription);
    }

    #[OA\Delete(
        path: '/api/v1/subscription',
        summary: 'Cancel back to Free',
        description: 'Free at once, ending any trial and stopping any card payments; the billing period stays. A trial used stays used.',
        security: [['sanctum' => []]],
        tags: ['Subscription'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts and messages in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'On Free now', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'You’re on the Free plan now.'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 502, description: '`payment_provider_error`: Paddle could not stop the card payments; nothing changed', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
        ],
    )]
    public function destroy(Request $request): JsonResponse
    {
        try {
            $subscription = $this->planChanger->cancel($request->user());
        } catch (PlanChangeRefused $refused) {
            return $refused->toResponse('subscription');
        }

        return (new SubscriptionResource($subscription))
            ->additional(['message' => __('subscription.cancelled')])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * The plan in place (200), or the payment page to pay for it (202).
     */
    private static function changed(Subscription|CheckoutSession $result): JsonResponse
    {
        return $result instanceof CheckoutSession
            ? (new CheckoutSessionResource($result))->response()->setStatusCode(202)
            : (new SubscriptionResource($result))->response()->setStatusCode(200);
    }
}
