<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Home', description: 'Single call to load everything the home/dashboard screen needs')]
class HomeController extends Controller
{
    #[OA\Get(
        path: '/api/v1/home',
        summary: 'Home screen — all dashboard data in one call',
        description: 'Returns the authenticated user\'s stats, all connected channels, the 10 most recent conversations (unread-first), the 5 most active automations, and the 10 latest unanswered comments. Use the `next_cursor` in `conversations` to load more.',
        security: [['sanctum' => []]],
        tags: ['Home'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard payload',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'business_name', type: 'string'),
                                    ]
                                ),
                                new OA\Property(property: 'stats', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'channels', type: 'integer', description: 'Total connected channels'),
                                        new OA\Property(property: 'active_automations', type: 'integer'),
                                        new OA\Property(property: 'open_conversations', type: 'integer'),
                                        new OA\Property(property: 'unread_messages', type: 'integer', description: 'Sum of unread_count across all open conversations'),
                                        new OA\Property(property: 'messages_today', type: 'integer', description: 'Outbound messages sent today'),
                                        new OA\Property(property: 'auto_replies_today', type: 'integer', description: 'Automated DMs + auto comment replies today'),
                                    ]
                                ),
                                new OA\Property(property: 'channels', type: 'array',
                                    description: 'All connected channels (usually few — no pagination needed)',
                                    items: new OA\Items(type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
                                            new OA\Property(property: 'username', type: 'string', nullable: true),
                                            new OA\Property(property: 'account_id', type: 'string'),
                                            new OA\Property(property: 'followers', type: 'integer', nullable: true),
                                            new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'expired']),
                                            new OA\Property(property: 'connected_at', type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'conversations', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'items', type: 'array',
                                            description: 'Top 10 conversations, unread first',
                                            items: new OA\Items(type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer'),
                                                    new OA\Property(property: 'contact_name', type: 'string'),
                                                    new OA\Property(property: 'contact_avatar', type: 'string', nullable: true),
                                                    new OA\Property(property: 'contact_platform_id', type: 'string'),
                                                    new OA\Property(property: 'platform', type: 'string'),
                                                    new OA\Property(property: 'channel_id', type: 'integer'),
                                                    new OA\Property(property: 'last_message', type: 'string', nullable: true),
                                                    new OA\Property(property: 'unread_count', type: 'integer'),
                                                    new OA\Property(property: 'status', type: 'string', enum: ['open', 'resolved']),
                                                    new OA\Property(property: 'last_message_at', type: 'string', format: 'date-time', nullable: true),
                                                ]
                                            )
                                        ),
                                        new OA\Property(property: 'has_more', type: 'boolean'),
                                        new OA\Property(property: 'next_cursor', type: 'string', nullable: true, description: 'Pass to GET /api/v1/conversations?cursor= to load more'),
                                    ]
                                ),
                                new OA\Property(property: 'automations', type: 'array',
                                    description: 'Top 5 active automations',
                                    items: new OA\Items(type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'trigger_type', type: 'string'),
                                            new OA\Property(property: 'active', type: 'boolean'),
                                            new OA\Property(property: 'channel', type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer'),
                                                    new OA\Property(property: 'platform', type: 'string'),
                                                    new OA\Property(property: 'username', type: 'string', nullable: true),
                                                ]
                                            ),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'recent_comments', type: 'array',
                                    description: '10 latest comments that have not been auto-replied yet',
                                    items: new OA\Items(type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'author_name', type: 'string'),
                                            new OA\Property(property: 'text', type: 'string'),
                                            new OA\Property(property: 'automated_reply', type: 'boolean'),
                                            new OA\Property(property: 'post', type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer'),
                                                    new OA\Property(property: 'caption', type: 'string', nullable: true),
                                                    new OA\Property(property: 'platform', type: 'string'),
                                                ]
                                            ),
                                            new OA\Property(property: 'posted_at', type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── Channels ─────────────────────────────────────────────────
        $channels = $user->channels()
            ->orderByDesc('connected_at')
            ->get();

        $channelIds = $channels->pluck('id');

        // ── Stats ─────────────────────────────────────────────────────
        $openConversations = Conversation::whereIn('channel_id', $channelIds)
            ->where('status', 'open');

        $messagesToday = Message::whereIn(
            'conversation_id',
            Conversation::whereIn('channel_id', $channelIds)->select('id')
        )
            ->where('direction', 'outbound')
            ->whereDate('sent_at', today());

        $autoRepliesToday = Message::whereIn(
            'conversation_id',
            Conversation::whereIn('channel_id', $channelIds)->select('id')
        )
            ->where('automated', true)
            ->whereDate('sent_at', today())
            ->count()
            + Comment::whereIn('channel_id', $channelIds)
            ->where('automated_reply', true)
            ->whereDate('posted_at', today())
            ->count();

        $stats = [
            'channels'           => $channels->count(),
            'active_automations' => $user->automations()->where('active', true)->count(),
            'open_conversations' => $openConversations->count(),
            'unread_messages'    => (int) $openConversations->sum('unread_count'),
            'messages_today'     => $messagesToday->count(),
            'auto_replies_today' => $autoRepliesToday,
        ];

        // ── Conversations (top 10, unread first) ─────────────────────
        $convPage = Conversation::with('channel:id,platform,username')
            ->whereIn('channel_id', $channelIds)
            ->orderByDesc('unread_count')
            ->orderByDesc('last_message_at')
            ->cursorPaginate(10);

        $conversations = [
            'items'       => $convPage->map(fn ($c) => [
                'id'                  => $c->id,
                'contact_name'        => $c->contact_name,
                'contact_avatar'      => $c->contact_avatar,
                'contact_platform_id' => $c->contact_platform_id,
                'platform'            => $c->channel?->platform,
                'channel_id'          => $c->channel_id,
                'last_message'        => $c->last_message,
                'unread_count'        => $c->unread_count,
                'status'              => $c->status,
                'last_message_at'     => $c->last_message_at?->toIso8601String(),
            ])->values(),
            'has_more'    => $convPage->hasMorePages(),
            'next_cursor' => $convPage->nextCursor()?->encode(),
        ];

        // ── Automations (top 5 active) ────────────────────────────────
        $automations = $user->automations()
            ->with('channel:id,platform,username')
            ->where('active', true)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'name'         => $a->name,
                'trigger_type' => $a->trigger_type,
                'active'       => $a->active,
                'channel'      => $a->channel ? [
                    'id'       => $a->channel->id,
                    'platform' => $a->channel->platform,
                    'username' => $a->channel->username,
                ] : null,
            ]);

        // ── Recent unanswered comments ───────────────────────────────
        $recentComments = Comment::with('post:id,caption,channel_id', 'post.channel:id,platform')
            ->whereIn('channel_id', $channelIds)
            ->where('automated_reply', false)
            ->orderByDesc('posted_at')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'author_name'    => $c->author_name,
                'text'           => $c->text,
                'automated_reply' => $c->automated_reply,
                'post'           => $c->post ? [
                    'id'       => $c->post->id,
                    'caption'  => $c->post->caption,
                    'platform' => $c->post->channel?->platform,
                ] : null,
                'posted_at'      => $c->posted_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'user' => [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'business_name' => $user->business_name,
                ],
                'stats'           => $stats,
                'channels'        => $channels->map(fn ($ch) => [
                    'id'           => $ch->id,
                    'platform'     => $ch->platform,
                    'username'     => $ch->username,
                    'account_id'   => $ch->account_id,
                    'followers'    => $ch->followers,
                    'status'       => $ch->status,
                    'connected_at' => $ch->connected_at?->toIso8601String(),
                ]),
                'conversations'   => $conversations,
                'automations'     => $automations,
                'recent_comments' => $recentComments,
            ],
        ]);
    }
}
