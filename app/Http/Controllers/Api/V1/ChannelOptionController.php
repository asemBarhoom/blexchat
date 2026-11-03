<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChannelOptionCollection;
use App\Models\ChannelOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ChannelOption', properties: [
    new OA\Property(property: 'id', type: 'string', enum: ChannelOption::PLATFORMS, example: 'instagram', description: 'Stable; pick the icon and the /onboarding/connect/:channel link by it'),
    new OA\Property(property: 'name', type: 'string', example: 'Instagram'),
    new OA\Property(property: 'line', type: 'string', example: 'Comments, DMs and story replies'),
    new OA\Property(property: 'connected', type: 'boolean', description: 'This owner already has an account on this platform'),
])]
class ChannelOptionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/onboarding/channels',
        summary: 'The channel picker (W05)',
        description: '"Where would you like to start?": the channels on offer, in the admin order, each marked when this owner has already connected it.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'The channels', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChannelOption')),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function __invoke(Request $request): ChannelOptionCollection
    {
        $userId = $request->user()->id;

        return new ChannelOptionCollection(
            ChannelOption::visible()
                ->withExists(['channels as connected' => fn (Builder $query): Builder => $query->where('user_id', $userId)])
                ->get(),
        );
    }
}
