<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Services\Referrals\ReferralProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReferralController extends Controller
{
    /** Commissions listed on the screen, newest first. */
    private const LISTED = 20;

    #[OA\Get(
        path: '/api/v1/referrals',
        summary: 'The owner’s referral link and earnings (D-7)',
        description: 'The link to share, the rules, and what it has earned so far. Money is in the currency’s units. While the program is closed `enabled` is false: show what was earned, but not the link.',
        security: [['sanctum' => []]],
        tags: ['Referrals'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']))],
        responses: [
            new OA\Response(response: 200, description: 'The referral screen', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', allOf: [
                    new OA\Schema(properties: [
                        new OA\Property(property: 'enabled', type: 'boolean'),
                        new OA\Property(property: 'code', type: 'string', example: 'K7Q2M9XP'),
                        new OA\Property(property: 'link', type: 'string', format: 'uri', example: 'https://blexchat.com/?ref=K7Q2M9XP'),
                        new OA\Property(property: 'payout_method', type: 'string', nullable: true, description: 'How and when referrers are paid'),
                        new OA\Property(property: 'stats', type: 'object', properties: [
                            new OA\Property(property: 'signed_up', type: 'integer', description: 'Accounts created through the link'),
                            new OA\Property(property: 'subscribed', type: 'integer', description: 'Of those, how many pay for a plan now'),
                            new OA\Property(property: 'earned', type: 'number', description: 'Every commission so far: pending + paid_out'),
                            new OA\Property(property: 'pending', type: 'number', description: 'Earned, not paid out yet'),
                            new OA\Property(property: 'paid_out', type: 'number'),
                        ]),
                        new OA\Property(property: 'commissions', type: 'array', description: 'The latest 20, newest first', items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'payment', type: 'number', example: 29, description: 'What the customer paid'),
                            new OA\Property(property: 'rate', type: 'number', example: 20, description: 'The rate when it was paid'),
                            new OA\Property(property: 'amount', type: 'number', example: 5.8),
                            new OA\Property(property: 'currency', type: 'string', example: 'usd'),
                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid']),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
                        ])),
                    ]),
                    new OA\Schema(ref: '#/components/schemas/ReferralTerms'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function __invoke(Request $request, ReferralProgram $program): JsonResponse
    {
        $owner = $request->user();

        return response()->json(['data' => [
            'enabled' => $program->isEnabled(),
            'code' => $owner->referralCode(),
            'link' => $program->link($owner),
            ...ReferralProgramController::terms($program),
            'payout_method' => $program->copy()['texts']['payout_method'] ?? null,
            'stats' => $program->summary($owner),
            'commissions' => $owner->referralCommissions()->latest('id')->limit(self::LISTED)->get()->map(fn (ReferralCommission $commission): array => [
                'id' => $commission->id,
                'payment' => $commission->payment_cents / 100,
                'rate' => $commission->rate,
                'amount' => $commission->amount_cents / 100,
                'currency' => $commission->currency,
                'status' => $commission->status,
                'created_at' => $commission->created_at?->toIso8601String(),
                'paid_at' => $commission->paid_at?->toIso8601String(),
            ])->all(),
        ]], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
