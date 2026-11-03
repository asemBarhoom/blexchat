<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChannelConnectScreenResource;
use App\Models\ChannelAccessRequest;
use App\Models\ChannelConnectScreen;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ChannelConnectScreen', properties: [
    new OA\Property(property: 'platform', type: 'string', enum: ChannelConnectScreen::PLATFORMS),
    new OA\Property(property: 'method', type: 'string', enum: ChannelConnectScreen::METHODS, description: 'Whose sign-in connects it: sets the button’s mark'),
    new OA\Property(property: 'name', type: 'string', example: 'Instagram'),
    new OA\Property(property: 'cta', type: 'string', example: 'Connect via Meta'),
    new OA\Property(property: 'lead', type: 'string'),
    new OA\Property(property: 'steps', type: 'array', items: new OA\Items(type: 'string'), example: ['Log in to Facebook', 'Choose your Instagram account', 'Review the permissions and save']),
    new OA\Property(property: 'options', type: 'array', description: '"See more options"', items: new OA\Items(properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'note', type: 'string', nullable: true),
        new OA\Property(property: 'url', type: 'string', nullable: true, description: 'Help page; null until there is one'),
    ])),
    new OA\Property(property: 'account_kind', type: 'string', nullable: true, example: 'Instagram · Professional account', description: 'Under the account handle on the taken and reconnect cards'),
    new OA\Property(property: 'can', type: 'string', nullable: true, example: 'answer comments and DMs on your account', description: 'What Blexchat can do once connected (W07)'),
    new OA\Property(property: 'reconnect_cta', type: 'string', example: 'Reconnect via Meta'),
    new OA\Property(property: 'waiting_in', type: 'string', example: 'the Meta window', description: 'connecting state: "Finish in …"'),
    new OA\Property(property: 'ineligible', type: 'object', properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'text', type: 'string'),
        new OA\Property(property: 'kind', type: 'string', nullable: true, example: 'Instagram · Personal account'),
        new OA\Property(property: 'how', type: 'string', nullable: true, example: 'How to switch to a professional account'),
        new OA\Property(property: 'help_url', type: 'string', nullable: true),
    ]),
    new OA\Property(property: 'state', type: 'string', enum: ['default', 'reconnect', 'requested', 'connected'], description: 'reconnect: this owner’s channel on this platform dropped and its automations are paused. requested: a Request access is waiting. connected: already active'),
    new OA\Property(property: 'channel', type: 'object', nullable: true, description: 'This owner’s channel on this platform (the active one first), as in GET /api/v1/dashboard'),
    new OA\Property(property: 'access_request', ref: '#/components/schemas/ChannelAccessRequest', nullable: true, description: 'The latest pending Request access for this platform'),
])]
class ChannelConnectScreenController extends Controller
{
    #[OA\Get(
        path: '/api/v1/onboarding/channels/{platform}/connect',
        summary: 'The connect-a-channel screen (W06)',
        description: 'The copy for one channel, and which state to open in. The denied, ineligible and taken states come from POST /api/v1/channels/instagram.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [
            new OA\Parameter(name: 'platform', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ChannelConnectScreen::PLATFORMS)),
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The screen', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ChannelConnectScreen'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 404, description: 'Unknown channel, or hidden by an admin'),
        ],
    )]
    public function __invoke(Request $request, string $platform): ChannelConnectScreenResource
    {
        $screen = ChannelConnectScreen::where('platform', $platform)->where('active', true)->firstOrFail();
        $user = $request->user();

        $channel = $user->channels()
            ->where('platform', $platform)
            ->orderByRaw("status = 'active' desc")
            ->latest('connected_at')
            ->first();

        $pendingRequest = ChannelAccessRequest::pending()
            ->where('user_id', $user->id)
            ->where('platform', $platform)
            ->latest('id')
            ->first();

        return new ChannelConnectScreenResource($screen, $channel, $pendingRequest);
    }
}
