<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\StoreChannelAccessRequest;
use App\Http\Resources\Api\V1\ChannelAccessRequestCollection;
use App\Http\Resources\Api\V1\ChannelAccessRequestResource;
use App\Models\ChannelAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ChannelAccessRequest', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'platform', type: 'string', example: 'instagram'),
    new OA\Property(property: 'account', type: 'object', properties: [new OA\Property(property: 'handle', type: 'string', nullable: true, example: 'nourboutique')]),
    new OA\Property(property: 'status', type: 'string', enum: ChannelAccessRequest::STATUSES),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'decided_at', type: 'string', format: 'date-time', nullable: true),
])]
class ChannelAccessRequestController extends Controller
{
    #[OA\Get(
        path: '/api/v1/channels/access-requests',
        summary: 'This owner’s Request access history',
        description: 'Newest first.',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        responses: [
            new OA\Response(response: 200, description: 'The requests', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ChannelAccessRequest')),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function index(Request $request): ChannelAccessRequestCollection
    {
        return new ChannelAccessRequestCollection(
            ChannelAccessRequest::where('user_id', $request->user()->id)->latest('id')->get(),
        );
    }

    #[OA\Post(
        path: '/api/v1/channels/access-requests',
        summary: 'Request access to an account another workspace connected (W06 taken → requested)',
        description: 'Send the `access_request_token` from the `account_taken` refusal. Asking again while a request is pending returns that request (200).',
        security: [['sanctum' => []]],
        tags: ['Channels'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the error messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token'], properties: [
            new OA\Property(property: 'token', type: 'string', description: '`access_request_token` from POST /api/v1/channels/instagram'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Request sent', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ChannelAccessRequest'),
            ])),
            new OA\Response(response: 200, description: 'Already asked; the pending request', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ChannelAccessRequest'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: 'The token is invalid, expired or another owner’s', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreChannelAccessRequest $request): JsonResponse
    {
        $channel = $request->channel();

        $accessRequest = ChannelAccessRequest::pending()->firstOrCreate(
            ['user_id' => $request->user()->id, 'channel_id' => $channel->id],
            ['platform' => $channel->platform, 'account_id' => $channel->account_id, 'account_handle' => $channel->username],
        );

        return (new ChannelAccessRequestResource($accessRequest))
            ->response()
            ->setStatusCode($accessRequest->wasRecentlyCreated ? 201 : 200);
    }
}
