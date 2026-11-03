<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\ShowConnectedChannelRequest;
use App\Http\Resources\Api\V1\ConnectedChannelResource;
use App\Models\Channel;
use App\Models\ChannelConnectedScreen;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ChannelConnectedScreen', description: 'The W07 copy for the channel’s platform, in the requested language', properties: [
    new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
    new OA\Property(property: 'channel_name', type: 'string', example: 'Instagram'),
    new OA\Property(property: 'title', type: 'string', example: 'Your Instagram is connected!'),
    new OA\Property(property: 'lead', type: 'string', example: 'Blexchat can now answer comments and DMs on your account. Next, a few quick questions so we can suggest your first automation.'),
    new OA\Property(property: 'account_kind', type: 'string', example: 'Instagram · Professional account', description: 'The line under the account handle'),
    new OA\Property(property: 'status_label', type: 'string', example: 'Connected'),
    new OA\Property(property: 'continue_label', type: 'string', example: 'Continue', description: 'The button to `next_step`'),
    new OA\Property(property: 'connect_another_label', type: 'string', example: 'Connect another channel', description: 'The link back to the channel picker (W05)'),
])]
#[OA\Schema(schema: 'ConnectedChannel', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 12),
    new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
    new OA\Property(property: 'account_id', type: 'string', example: '17841400000'),
    new OA\Property(property: 'username', type: 'string', nullable: true, example: '@nourboutique'),
    new OA\Property(property: 'profile_picture', type: 'string', format: 'uri', nullable: true),
    new OA\Property(property: 'followers', type: 'integer', example: 12400),
    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'expired']),
    new OA\Property(property: 'connected_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'token_expires_at', type: 'string', format: 'date-time', nullable: true),
    new OA\Property(property: 'screen', ref: '#/components/schemas/ChannelConnectedScreen', nullable: true, description: 'Null when the admin has no copy for this platform or switched it off'),
    new OA\Property(property: 'next_step', type: 'string', enum: ['channel', 'survey', 'plan', 'done'], description: 'Where Continue goes, as in GET /api/v1/onboarding'),
])]
class ConnectedChannelController extends Controller
{
    #[OA\Get(
        path: '/api/v1/channels/{channel}',
        summary: 'One connected channel, for the "channel connected" screen (W07)',
        description: 'The connected account (handle, picture, followers) with the success copy for its platform. Only the owner may read it.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [
            new OA\Parameter(name: 'channel', in: 'path', required: true, description: 'The channel id, as returned when it connected', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The channel', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ConnectedChannel'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another owner’s channel'),
            new OA\Response(response: 404, description: 'No such channel'),
        ],
    )]
    public function __invoke(ShowConnectedChannelRequest $request, Channel $channel): ConnectedChannelResource
    {
        return new ConnectedChannelResource($channel, ChannelConnectedScreen::forPlatform($channel->platform));
    }
}
