<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Checkout\CheckCouponRequest;
use App\Http\Resources\Api\V1\CouponQuoteResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Checkout', description: 'The checkout modal: tiers, trial reminder and coupons')]
#[OA\Schema(schema: 'CouponQuote', properties: [
    new OA\Property(property: 'code', type: 'string', example: 'BLEX20', description: 'The code as stored: upper-case'),
    new OA\Property(property: 'percent_off', type: 'integer', example: 20, description: '"BLEX20 — 20% off"'),
    new OA\Property(property: 'plan', type: 'string', example: 'pro'),
    new OA\Property(property: 'billing', type: 'string', enum: ['monthly', 'annual']),
    new OA\Property(property: 'list_price', type: 'number', example: 39, description: 'US dollars a month before the coupon'),
    new OA\Property(property: 'discount', type: 'number', example: 7.8, description: 'US dollars a month taken off, rounded to the cent'),
    new OA\Property(property: 'price', type: 'number', example: 31.2, description: 'US dollars a month after the coupon'),
    new OA\Property(property: 'billed_total', type: 'number', example: 31.2, description: 'What one billing period charges: twelve months when billed yearly'),
])]
#[OA\Schema(schema: 'CouponError', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', description: 'Ready to show under the coupon field, in the requested language'),
    new OA\Property(property: 'code', type: 'string', enum: [Coupon::INVALID, Coupon::EXPIRED, Coupon::NOT_FOR_PLAN], description: 'Stable value to branch on'),
])]
class CouponController extends Controller
{
    #[OA\Post(
        path: '/api/v1/coupons/check',
        summary: 'Check a coupon code for a plan',
        description: <<<'MD'
            The "Apply coupon" button in the checkout. Codes match ignoring case. Nothing is redeemed:
            card payments are not built yet, so this only checks the code and prices the plan with it.

            There is no coupon on Free or on a plan sold with a free trial (`coupon_not_for_plan`).
            MD,
        security: [['sanctum' => []]],
        tags: ['Checkout'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the error messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['code', 'plan'], properties: [
            new OA\Property(property: 'code', type: 'string', example: 'blex20'),
            new OA\Property(property: 'plan', type: 'string', example: 'pro', description: 'A plan `id` from GET /api/v1/plans'),
            new OA\Property(property: 'billing', type: 'string', enum: ['monthly', 'annual'], default: 'monthly'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'The code applies', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CouponQuote'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: '`coupon_invalid`, `coupon_expired` or `coupon_not_for_plan` (CouponError); or an unknown plan or billing period (ValidationError)', content: new OA\JsonContent(ref: '#/components/schemas/CouponError')),
            new OA\Response(response: 429, description: 'Too many checks; try again in a minute'),
        ],
    )]
    public function check(CheckCouponRequest $request): JsonResponse
    {
        $plan = $request->plan();
        $coupon = Coupon::findByCode($request->string('code'));
        $rejection = $coupon === null ? Coupon::INVALID : $coupon->rejectionFor($plan);

        if ($rejection !== null) {
            // A used-up code shares coupon_expired's code, but says what happened.
            $message = $rejection === Coupon::EXPIRED && ! $coupon->isExpired() ? 'coupon_used_up' : $rejection;

            return response()->json([
                'message' => __("checkout.{$message}", ['plan' => $plan->name]),
                'code' => $rejection,
            ], 422);
        }

        return (new CouponQuoteResource($coupon))->forPlan($plan, $request->billing())->response();
    }
}
