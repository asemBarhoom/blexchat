<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\SimulateChannelEventRequest;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Services\Instagram\FakeInstagramClient;
use App\Services\Instagram\InstagramEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ChannelTestEventController extends Controller
{
    #[OA\Post(
        path: '/api/v1/channels/{channel}/test-events',
        summary: 'Simulate a comment or DM on a test channel (development only)',
        description: <<<'MD'
            Available only while `INSTAGRAM_DRIVER=fake`; 404 otherwise. Feeds the event through the same handling as Instagram's webhook, so the matching automation answers (its reply is logged, not sent), the contact is recorded and the inbox fills.

            - `comment`: a comment on `post` (a post id from **GET /api/v1/channels/{channel}/posts**; the newest post when left out).
            - `message`: a DM from `from`.
            MD,
        security: [['sanctum' => []]],
        tags: ['Channels'],
        parameters: [new OA\Parameter(name: 'channel', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['type', 'text'], properties: [
            new OA\Property(property: 'type', type: 'string', enum: ['comment', 'message']),
            new OA\Property(property: 'text', type: 'string', maxLength: 1000, example: 'price'),
            new OA\Property(property: 'from', type: 'string', maxLength: 30, description: 'The customer’s Instagram username', example: 'lulu.styles'),
            new OA\Property(property: 'post', type: 'string', description: 'Comments only: the post’s platform id', example: 'fake-1-post-1'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'The event was handled', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['comment', 'message']),
                    new OA\Property(property: 'comment_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'conversation_id', type: 'integer', nullable: true, description: 'The DM thread, or the one the private reply opened'),
                    new OA\Property(property: 'automation_id', type: 'integer', nullable: true, description: 'The automation that answered, if any'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another owner’s channel'),
            new OA\Response(response: 404, description: 'No such channel, or the fake Instagram driver is off'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function __invoke(SimulateChannelEventRequest $request, Channel $channel, InstagramEventHandler $events, FakeInstagramClient $instagram): JsonResponse
    {
        abort_unless(FakeInstagramClient::active() && $channel->platform === 'instagram', 404);

        $customerId = 'fake-customer-'.$request->from();
        $eventId = 'fake_'.Str::random(16);

        if ($request->input('type') === 'comment') {
            $post = $request->input('post', $instagram->recentMedia($channel, 1)[0]['id']);
            $events->comment($channel, [
                'id' => $eventId,
                'text' => $request->input('text'),
                'from' => ['id' => $customerId, 'username' => $request->from()],
                'media' => ['id' => $post, 'media_product_type' => $instagram->media($channel, $post)['media_product_type'] ?? 'FEED'],
            ]);

            $comment = Comment::where('channel_id', $channel->id)->where('platform_comment_id', $eventId)->firstOrFail();

            return response()->json(['data' => [
                'type' => 'comment',
                'comment_id' => $comment->id,
                'conversation_id' => Conversation::where('channel_id', $channel->id)->where('contact_platform_id', $customerId)->value('id'),
                'automation_id' => $comment->automation_id,
            ]], 201);
        }

        $events->message($channel, [
            'sender' => ['id' => $customerId],
            'recipient' => ['id' => $channel->account_id],
            'timestamp' => now()->getTimestampMs(),
            'message' => ['mid' => $eventId, 'text' => $request->input('text')],
        ]);

        $conversation = Conversation::where('channel_id', $channel->id)->where('platform_conversation_id', $customerId)->firstOrFail();

        return response()->json(['data' => [
            'type' => 'message',
            'comment_id' => null,
            'conversation_id' => $conversation->id,
            'automation_id' => $conversation->messages()->where('automated', true)->latest('id')->value('automation_id'),
        ]], 201);
    }
}
