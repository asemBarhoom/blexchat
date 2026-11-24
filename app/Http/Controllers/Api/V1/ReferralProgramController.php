<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Referrals\ReferralProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ReferralTerms', description: 'The commission rules, as set in the admin', properties: [
    new OA\Property(property: 'rate', type: 'number', example: 20, description: 'Percent of each counted payment'),
    new OA\Property(property: 'terms', type: 'string', example: '20% of their first payment', description: 'The rules as one phrase in the requested language'),
    new OA\Property(property: 'commission', type: 'object', properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['first_payment', 'recurring']),
        new OA\Property(property: 'months', type: 'integer', nullable: true, description: 'recurring only: the months after the first payment that earn; 0 for as long as the customer pays. Null for first_payment'),
    ]),
    new OA\Property(property: 'cookie_days', type: 'integer', example: 30, description: 'How long to keep `ref` from a visit before sign-up'),
    new OA\Property(property: 'currency', type: 'string', example: 'usd'),
    new OA\Property(property: 'min_payout', type: 'number', example: 0, description: 'Least a referrer must have earned before a payout; 0 for none'),
])]
class ReferralProgramController extends Controller
{
    #[OA\Get(
        path: '/api/v1/referral-program',
        summary: 'The public referral program page (/referral)',
        description: <<<'MD'
            The page's copy and the commission rules, all set in the admin. No authentication.

            - 404 while the program is closed: hide the page, and drop links to `/referral` (GET /home leaves them out then, and says `referral_program_enabled: false`).
            - The rate, the rules, the days and the top earning in the copy are filled in already; `terms`, `rate`, `commission` and `earnings` are there for anything the page draws itself.
            - **Tracking:** keep `ref` from `/?ref=CODE` for `cookie_days`, and send it as `ref` to POST /auth/email-link, or as `?ref=` to GET /auth/google. Only a new account counts.
            MD,
        tags: ['Referrals'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'The page', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', allOf: [
                    new OA\Schema(properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'Earn by sharing Blexchat'),
                        new OA\Property(property: 'lead', type: 'string'),
                        new OA\Property(property: 'steps', type: 'array', description: 'How it works, in order', items: new OA\Items(properties: [
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'text', type: 'string'),
                        ])),
                        new OA\Property(property: 'benefits', type: 'array', description: 'Why join', items: new OA\Items(properties: [
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'text', type: 'string'),
                        ])),
                        new OA\Property(property: 'earnings_heading', type: 'string', example: 'What one sale earns you'),
                        new OA\Property(property: 'earnings', type: 'array', description: 'One row per plan on the payment page, worked out from today’s prices and rate', items: new OA\Items(properties: [
                            new OA\Property(property: 'plan', type: 'string', example: 'pro'),
                            new OA\Property(property: 'name', type: 'string', example: 'Pro'),
                            new OA\Property(property: 'monthly', type: 'number', example: 7.8, description: 'Earned on a monthly payment'),
                            new OA\Property(property: 'annual', type: 'number', example: 69.6, description: 'Earned on a yearly payment'),
                        ])),
                        new OA\Property(property: 'faq', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'question', type: 'string'),
                            new OA\Property(property: 'answer', type: 'string'),
                        ])),
                        new OA\Property(property: 'cta', ref: '#/components/schemas/Cta'),
                        new OA\Property(property: 'payout_method', type: 'string', description: 'How and when referrers are paid'),
                    ]),
                    new OA\Schema(ref: '#/components/schemas/ReferralTerms'),
                ]),
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'locale', type: 'string', enum: ['en', 'ar']),
                    new OA\Property(property: 'fallback', type: 'boolean', description: 'True when a text is not translated yet and is served in English'),
                    new OA\Property(property: 'available_locales', type: 'array', items: new OA\Items(type: 'string')),
                ]),
            ])),
            new OA\Response(response: 404, description: 'The program is closed'),
        ],
    )]
    public function __invoke(ReferralProgram $program): JsonResponse
    {
        $copy = $program->isEnabled() ? $program->copy() : null;

        abort_if($copy === null, 404);

        $texts = $copy['texts'];

        return response()->json([
            'data' => [
                'title' => $texts['title'] ?? null,
                'lead' => $texts['lead'] ?? null,
                'steps' => array_values($texts['steps'] ?? []),
                'benefits' => array_values($texts['benefits'] ?? []),
                'earnings_heading' => $texts['earnings_heading'] ?? null,
                'earnings' => $program->earnings(),
                'faq' => array_values($texts['faq'] ?? []),
                'cta' => ['label' => $texts['cta']['label'] ?? null, 'to' => $texts['cta']['url'] ?? '/signup'],
                'payout_method' => $texts['payout_method'] ?? null,
                ...self::terms($program),
            ],
            'meta' => [
                'locale' => App::getLocale(),
                'fallback' => $copy['fallback'],
                'available_locales' => config('app.supported_locales'),
            ],
        ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->header('Content-Language', App::getLocale());
    }

    /**
     * @return array{rate: float, terms: string, commission: array{type: string, months: int|null}, cookie_days: int, currency: string, min_payout: float}
     */
    public static function terms(ReferralProgram $program): array
    {
        return [
            'rate' => $program->rate(),
            'terms' => $program->terms(),
            'commission' => $program->commission(),
            'cookie_days' => $program->cookieDays(),
            'currency' => $program->currency(),
            'min_payout' => $program->minPayout(),
        ];
    }
}
