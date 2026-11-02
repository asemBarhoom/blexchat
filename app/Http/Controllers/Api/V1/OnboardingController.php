<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OnboardingResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Onboarding', description: 'After a new account signs in: connect a channel (W05–W07, see Channels), the survey (W08), then a plan (W09)')]
#[OA\Schema(schema: 'Onboarding', properties: [
    new OA\Property(property: 'next_step', type: 'string', enum: ['channel', 'survey', 'plan', 'done'], description: 'The first step not done yet. channel: /onboarding/channel, survey: /onboarding/about, plan: /onboarding/plans, done: /onboarding/first-move or the app'),
    new OA\Property(property: 'channels', type: 'array', items: new OA\Items(type: 'object'), description: 'Connected channels, as in GET /api/v1/dashboard'),
    new OA\Property(property: 'survey_completed_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'subscription', ref: '#/components/schemas/Subscription', nullable: true),
])]
class OnboardingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/onboarding',
        summary: 'Where the owner is in onboarding',
        description: 'Call it after sign-in when `is_new_user` is true, or on a reload mid-onboarding, to resume at `next_step`. A step skipped on the frontend stays not done here.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'Onboarding progress', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Onboarding'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function __invoke(Request $request): OnboardingResource
    {
        return new OnboardingResource($request->user()->load(['channels', 'subscription.plan']));
    }
}
