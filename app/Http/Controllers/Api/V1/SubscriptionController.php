<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\ChoosePlanRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Services\Stripe\StripeCheckout;
use App\Services\Stripe\StripeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Subscription', properties: [
    new OA\Property(property: 'plan', ref: '#/components/schemas/Plan', description: 'The tier in force today: Free once a trial has ended'),
    new OA\Property(property: 'chosen_plan', type: 'string', example: 'essential', description: 'The plan picked, even after its trial ended'),
    new OA\Property(property: 'billing_period', type: 'string', enum: ['monthly', 'annual']),
    new OA\Property(property: 'status', type: 'string', enum: ['active', 'trialing', 'trial_ended'], description: 'trial_ended: the trial ran out and was not bought (S6.4), so `plan` is Free'),
    new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'trial_days_left', type: 'integer', nullable: true, example: 14, description: 'While on a trial only'),
    new OA\Property(property: 'paid_by_card', type: 'boolean', example: false, description: 'A Stripe subscription pays for the plan and renews it'),
    new OA\Property(property: 'current_period_end', type: 'string', format: 'date-time', nullable: true, description: 'When the paid period renews; card plans only'),
])]
#[OA\Schema(schema: 'PlanError', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', description: 'Ready to show, in the requested language'),
    new OA\Property(property: 'code', type: 'string', enum: ['payment_required', 'trial_used'], description: 'Stable value to branch on'),
])]
class SubscriptionController extends Controller
{
    #[OA\Post(
        path: '/api/v1/onboarding/plan',
        summary: 'Choose a plan (W09)',
        description: <<<'MD'
            - **Free** ("Continue with Free"): active at once.
            - **A plan with `trial_days`** ("Start free trial" in the checkout): a trial starts and no card is taken. It does not convert: when it ends the account is on Free again. One trial per account.
            - **Any other paid plan**, and the plan sold with a trial once the account has had its trial: answers 202 with a
              Stripe payment page (`data.url`, as POST /api/v1/checkout/session). The plan changes once it is paid.
              Without a Stripe key on the server it answers 402 `payment_required` (409 `trial_used` after the trial) instead.

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
            new OA\Response(response: 402, description: '`payment_required`: a paid plan, and no Stripe key on this server', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 409, description: '`trial_used`: this account already had its free trial, and no Stripe key on this server', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 422, description: 'Unknown plan or billing period', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 502, description: '`payment_provider_error`: Stripe could not be reached', content: new OA\JsonContent(ref: '#/components/schemas/CheckoutError')),
        ],
    )]
    public function store(ChoosePlanRequest $request, StripeCheckout $checkout): JsonResponse
    {
        $plan = $request->plan();
        $user = $request->user();
        $current = $user->subscription()->first();

        // Free, and the free trial, take no card; every other plan is paid on the Stripe payment page.
        if (! $plan->isFree() && ! $user->canStartTrialOn($plan)) {
            if ($current?->paidByCard() && $current->plan_id === $plan->id && $current->billing_period === $request->billing()) {
                return (new SubscriptionResource($current->load('plan')))->response()->setStatusCode(200);
            }

            if (! StripeClient::isConfigured()) {
                return $plan->hasTrial() ? $this->planError(409, 'trial_used') : $this->planError(402, 'payment_required');
            }

            return CheckoutSessionController::startCheckout($checkout, $request, $plan, $request->billing(), null, 202);
        }

        if ($current?->paidByCard()) {
            try {
                $checkout->cancel($current);
            } catch (RequestException|ConnectionException $exception) {
                report($exception);

                return CheckoutSessionController::error(502, 'payment_provider_error');
            }
        }

        // Picking the plan you are already trying keeps the trial's clock as it is.
        $keepsTrial = $current?->onTrial() === true && $current->plan_id === $plan->id;

        $state = match (true) {
            $keepsTrial => [],
            $plan->hasTrial() => ['status' => 'trialing', 'trial_ends_at' => now()->addDays($plan->trial_days), 'trial_used_at' => now()],
            default => ['status' => 'active', 'trial_ends_at' => null],
        };

        $subscription = $user->subscription()->updateOrCreate([], [
            'plan_id' => $plan->id,
            'billing_period' => $request->billing(),
            ...$state,
        ]);

        return (new SubscriptionResource($subscription->load('plan')))->response()->setStatusCode(200);
    }

    private function planError(int $status, string $code): JsonResponse
    {
        return response()->json(['message' => __("onboarding.plan.{$code}"), 'code' => $code], $status);
    }
}
