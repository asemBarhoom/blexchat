<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\ChoosePlanRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Subscription', properties: [
    new OA\Property(property: 'plan', ref: '#/components/schemas/Plan', description: 'The tier in force today: Free once a trial has ended'),
    new OA\Property(property: 'chosen_plan', type: 'string', example: 'essential', description: 'The plan picked, even after its trial ended'),
    new OA\Property(property: 'billing_period', type: 'string', enum: ['monthly', 'annual']),
    new OA\Property(property: 'status', type: 'string', enum: ['active', 'trialing', 'trial_ended'], description: 'trial_ended: the trial ran out and was not bought (S6.4), so `plan` is Free'),
    new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'trial_days_left', type: 'integer', nullable: true, example: 14, description: 'While on a trial only'),
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
            - **Any other paid plan** answers 402 `payment_required`: card payments are not built yet.

            Choosing again replaces the current plan.
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
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 402, description: '`payment_required`: a paid plan without a trial', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 409, description: '`trial_used`: this account already had its free trial', content: new OA\JsonContent(ref: '#/components/schemas/PlanError')),
            new OA\Response(response: 422, description: 'Unknown plan or billing period', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(ChoosePlanRequest $request): JsonResponse
    {
        $plan = $request->plan();
        $current = $request->user()->subscription()->first();

        if (! $plan->isFree() && ! $plan->hasTrial()) {
            return $this->planError(402, 'payment_required');
        }

        // Picking the plan you are already trying keeps the trial's clock as it is.
        $keepsTrial = $current?->onTrial() === true && $current->plan_id === $plan->id;

        if ($plan->hasTrial() && ! $keepsTrial && $current?->trial_used_at !== null) {
            return $this->planError(409, 'trial_used');
        }

        $state = match (true) {
            $keepsTrial => [],
            $plan->hasTrial() => ['status' => 'trialing', 'trial_ends_at' => now()->addDays($plan->trial_days), 'trial_used_at' => now()],
            default => ['status' => 'active', 'trial_ends_at' => null],
        };

        $subscription = $request->user()->subscription()->updateOrCreate([], [
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
