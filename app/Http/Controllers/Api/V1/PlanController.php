<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanCollection;
use App\Models\Plan;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Plans', description: 'The plan ladder, for the pricing page and the onboarding plan screen (W09)')]
#[OA\Schema(schema: 'Plan', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'essential', description: 'Stable id; send it as `plan` to POST /api/v1/onboarding/plan'),
    new OA\Property(property: 'name', type: 'string', example: 'Essential'),
    new OA\Property(property: 'tagline', type: 'string', nullable: true, example: 'For a growing shop or creator.'),
    new OA\Property(property: 'monthly_price', type: 'integer', example: 17, description: 'Whole US dollars a month, billed monthly'),
    new OA\Property(property: 'annual_price', type: 'integer', example: 14, description: 'Whole US dollars a month, billed yearly'),
    new OA\Property(property: 'annual_total', type: 'integer', example: 168, description: 'What one year costs: "Billed $168 yearly"'),
    new OA\Property(property: 'currency', type: 'string', enum: ['USD']),
    new OA\Property(property: 'contacts_limit', type: 'integer', example: 250, description: 'Never unlimited'),
    new OA\Property(property: 'has_ai', type: 'boolean', description: 'Shows the "+ Blexchat AI" row'),
    new OA\Property(property: 'has_email_capture', type: 'boolean', description: 'Includes email capture: the automation extra ask_email and the dashboard leads card'),
    new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string'), example: ['All four channels', 'Unlimited automations']),
    new OA\Property(property: 'trial_days', type: 'integer', example: 14, description: '0: no trial, the plan is charged on the day'),
    new OA\Property(property: 'is_free', type: 'boolean'),
    new OA\Property(property: 'is_recommended', type: 'boolean', description: '"Our pick for you"'),
])]
class PlanController extends Controller
{
    #[OA\Get(
        path: '/api/v1/plans',
        summary: 'The plan ladder',
        description: 'Every plan on sale, in the admin order. Public: the pricing page can use it too.',
        tags: ['Plans'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [new OA\Response(response: 200, description: 'The plans', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Plan')),
        ]))],
    )]
    public function __invoke(): PlanCollection
    {
        return new PlanCollection(Plan::visible()->get());
    }
}
