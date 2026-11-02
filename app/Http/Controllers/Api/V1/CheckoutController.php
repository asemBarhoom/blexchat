<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CheckoutResource;
use App\Models\Plan;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CheckoutSettings', properties: [
    new OA\Property(property: 'plans', type: 'array', items: new OA\Items(ref: '#/components/schemas/Plan'), description: 'The tiers the checkout switcher offers, in the admin order'),
    new OA\Property(property: 'trial_reminder_days', type: 'integer', example: 7, description: 'The day of a free trial on which the reminder email goes out: the middle step of the trial timeline'),
    new OA\Property(property: 'annual_saving_up_to', type: 'integer', example: 30, description: 'Whole percent: "Save up to 30%". Computed from the plans on sale'),
    new OA\Property(property: 'coupons_on_trial', type: 'boolean', example: false, description: 'Always false: hide "Apply coupon" on a trial'),
])]
class CheckoutController extends Controller
{
    #[OA\Get(
        path: '/api/v1/checkout',
        summary: 'Checkout settings',
        description: 'What the checkout modal needs besides the plan ladder: the tiers it offers and the trial reminder day. Public.',
        tags: ['Checkout'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [new OA\Response(response: 200, description: 'The settings', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/CheckoutSettings'),
        ]))],
    )]
    public function __invoke(): CheckoutResource
    {
        return new CheckoutResource(Plan::visible()->get());
    }
}
