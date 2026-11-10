<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Channel\SimulateChannelEventRequest;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Services\Channels\ChannelEventHandler;
use App\Services\Channels\MessagingClient;
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
    public function __invoke(SimulateChannelEventRequest $request, Channel $channel, InstagramEventHandler $events, FakeInstagramClient $instagram, ChannelEventHandler $handler): JsonResponse
    {
        if (in_array($channel->platform, MessagingClient::PLATFORMS, true)) {
            return $this->otherChannel($request, $channel, $handler);
        }

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
        $inbound = $conversation->messages()->where('platform_message_id', $eventId)->firstOrFail();

        return response()->json(['data' => [
            'type' => 'message',
            'comment_id' => null,
            'conversation_id' => $conversation->id,
            // Only a reply to this message: earlier ones in the thread were answers to something else.
            'automation_id' => $conversation->messages()->where('automated', true)->where('id', '>', $inbound->id)->value('automation_id'),
        ]], 201);
    }

    /**
     * WhatsApp, Messenger and TikTok (MESSAGING_DRIVER=fake): an incoming message only; their
     * conversations wait for the owner.
     */
    private function otherChannel(SimulateChannelEventRequest $request, Channel $channel, ChannelEventHandler $handler): JsonResponse
    {
        abort_unless(MessagingClient::fakeActive(), 404);
        abort_if(! in_array($request->input('type'), ['message', 'comment'], true), 422, __('channels.test_event_unsupported', ['platform' => __("channels.platforms.{$channel->platform}")]));

        $customerId = $channel->platform === 'whatsapp' ? '9665'.substr((string) crc32($request->from()), 0, 8) : 'fake-customer-'.$request->from();
        if ($request->input('type') === 'comment') {
            $handler->comment($channel, 'fake_'.Str::random(16), $customerId, $request->input('text'), $request->from(), $request->input('post'));
        } else {
            $handler->message($channel, $customerId, 'fake_'.Str::random(16), $request->input('text'), $request->from(), $channel->platform === 'whatsapp' ? '+'.$customerId : null);
        }

        return response()->json(['data' => [
            'type' => 'message',
            'comment_id' => null,
            'conversation_id' => Conversation::where('channel_id', $channel->id)->where('platform_conversation_id', $customerId)->value('id'),
            'automation_id' => null,
        ]], 201);
    }
}
