<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Message\SendMessageRequest;
use App\Http\Resources\Api\V1\ConversationCollection;
use App\Http\Resources\Api\V1\MessageCollection;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Conversations', description: 'DM conversations across connected channels')]
class ConversationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/conversations',
        summary: 'List DM conversations',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [
            new OA\Parameter(name: 'channel_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'resolved', 'all'])),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Conversation list')]
    )]
    public function index(Request $request): ConversationCollection
    {
        $channelIds = $request->user()->channels()->pluck('id');

        $query = Conversation::with('channel')
            ->whereIn('channel_id', $channelIds)
            ->orderByDesc('last_message_at');

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return new ConversationCollection(
            $query->cursorPaginate($request->integer('limit', 20))
        );
    }

    #[OA\Get(
        path: '/api/v1/conversations/{id}/messages',
        summary: 'Get messages in a conversation',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Message thread'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function messages(Request $request, Conversation $conversation): MessageCollection
    {
        abort_unless(
            $request->user()->channels()->where('id', $conversation->channel_id)->exists(),
            403
        );

        $messages = $conversation->messages()
            ->orderByDesc('sent_at')
            ->limit($request->integer('limit', 30))
            ->get();

        return new MessageCollection($messages);
    }

    #[OA\Post(
        path: '/api/v1/conversations/{id}/messages',
        summary: 'Send a message in a conversation',
        security: [['sanctum' => []]],
        tags: ['Conversations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'content'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['text', 'text_with_button']),
                    new OA\Property(property: 'content', type: 'string'),
                    new OA\Property(property: 'button_label', type: 'string'),
                    new OA\Property(property: 'button_url', type: 'string', format: 'url'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message sent'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 502, description: 'Instagram refused the message (e.g. the 24-hour messaging window has closed)'),
        ]
    )]
    public function sendMessage(SendMessageRequest $request, Conversation $conversation, InstagramClient $instagram): JsonResponse
    {
        abort_unless(
            $request->user()->channels()->where('id', $conversation->channel_id)->exists(),
            403
        );

        $platformMessageId = null;

        if ($conversation->channel->platform === 'instagram') {
            try {
                $platformMessageId = $instagram->sendMessage(
                    $conversation->channel,
                    $conversation->contact_platform_id,
                    InstagramClient::message($request->content, $request->button_label, $request->button_url),
                );
            } catch (RequestException|ConnectionException $exception) {
                return response()->json([
                    'message' => 'Instagram did not deliver the message.',
                    'error' => $exception instanceof RequestException ? InstagramClient::errorMessage($exception) : null,
                ], 502);
            }
        }

        $message = $conversation->messages()->create([
            'platform_message_id' => $platformMessageId,
            'direction' => 'outbound',
            'type' => $request->type,
            'content' => $request->content,
            'button_label' => $request->button_label,
            'button_url' => $request->button_url,
            'automated' => false,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message' => $request->content,
            'last_message_at' => now(),
        ]);

        return response()->json(['data' => new MessageResource($message)], 201);
    }
}
