<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Comment\ReplyCommentRequest;
use App\Http\Requests\Api\Comment\SendDmRequest;
use App\Http\Resources\Api\V1\CommentCollection;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Comments', description: 'Monitor and reply to post comments')]
class CommentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/posts/{postId}/comments',
        summary: 'List comments on a post',
        security: [['sanctum' => []]],
        tags: ['Comments'],
        parameters: [
            new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'automated_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Comment list')]
    )]
    public function index(Request $request, Post $post): CommentCollection
    {
        abort_unless(
            $request->user()->channels()->where('id', $post->channel_id)->exists(),
            403
        );

        $query = $post->comments();

        if ($request->boolean('automated_only')) {
            $query->where('automated_reply', true);
        }

        return new CommentCollection($query->orderByDesc('posted_at')->paginate(50));
    }

    #[OA\Post(
        path: '/api/v1/comments/{commentId}/reply',
        summary: 'Reply to a comment publicly',
        security: [['sanctum' => []]],
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [new OA\Property(property: 'message', type: 'string', maxLength: 2200)]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reply posted'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function reply(ReplyCommentRequest $request, Comment $comment): JsonResponse
    {
        abort_unless(
            $request->user()->channels()->where('id', $comment->channel_id)->exists(),
            403
        );

        $reply = Comment::create([
            'post_id'             => $comment->post_id,
            'channel_id'          => $comment->channel_id,
            'platform_comment_id' => 'reply_'.uniqid(),
            'author_name'         => 'Business',
            'author_platform_id'  => 'business',
            'text'                => $request->message,
            'automated_reply'     => false,
            'posted_at'           => now(),
        ]);

        return response()->json([
            'data' => ['reply_id' => $reply->id, 'posted_at' => $reply->posted_at->toIso8601String()],
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/comments/{commentId}/send-dm',
        summary: 'Reply to a commenter via DM',
        security: [['sanctum' => []]],
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', maxLength: 2200),
                    new OA\Property(property: 'include_public_reply', type: 'boolean'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'DM sent')]
    )]
    public function sendDm(SendDmRequest $request, Comment $comment): JsonResponse
    {
        abort_unless(
            $request->user()->channels()->where('id', $comment->channel_id)->exists(),
            403
        );

        $conversation = Conversation::firstOrCreate(
            [
                'channel_id'          => $comment->channel_id,
                'contact_platform_id' => $comment->author_platform_id,
            ],
            [
                'platform_conversation_id' => 'conv_'.uniqid(),
                'contact_name'             => $comment->author_name,
                'status'                   => 'open',
            ]
        );

        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'type'      => 'text',
            'content'   => $request->message,
            'automated' => false,
            'sent_at'   => now(),
        ]);

        return response()->json([
            'data' => ['dm_id' => $message->id, 'conversation_id' => $conversation->id],
        ], 201);
    }
}
