<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Home', description: 'Single call for everything the home/dashboard screen needs')]
class HomeController extends Controller
{
    #[OA\Get(
        path: '/api/v1/home',
        summary: 'Home screen — one request, all data',
        description: 'Returns the user info, connected channels, automations list, checklist progress, automation template suggestions, and last-7-day metrics. Designed for the Home screen: nothing more, nothing less.',
        security: [['sanctum' => []]],
        tags: ['Home'],
        responses: [
            new OA\Response(response: 200, description: 'Home payload', content: new OA\JsonContent(ref: '#/components/schemas/HomeResponse')),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user       = $request->user();
        $channels   = $user->channels()->orderByDesc('connected_at')->get();
        $channelIds = $channels->pluck('id');

        // ── Automations ───────────────────────────────────────────────
        $automations = $user->automations()
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'name'         => $a->name,
                'status'       => $a->active ? 'live' : 'stopped',
                'trigger_type' => $a->trigger_type,
                'runs'         => $a->messages_count ?? null,
            ]);

        // ── Checklist progress ────────────────────────────────────────
        $channelConnected  = $channels->isNotEmpty();
        $automationBuilt   = $automations->isNotEmpty();

        $checklist = [
            'steps' => [
                [
                    'id'    => 'connect',
                    'label' => 'Connect a channel',
                    'done'  => $channelConnected,
                    'to'    => '/onboarding/channel',
                ],
                [
                    'id'    => 'automation',
                    'label' => 'Build your first automation',
                    'done'  => $automationBuilt,
                    'to'    => '/onboarding/automation/new?trigger=comment',
                ],
                [
                    'id'    => 'test',
                    'label' => 'Send yourself a test comment',
                    'done'  => false, // client-side flag, always false from API
                    'to'    => '/app/automation',
                ],
            ],
        ];

        // ── "Start here" automation templates ────────────────────────
        $startHere = [
            'title' => 'Start here',
            'lead'  => 'Three moves that pay for themselves in a week.',
            'templates' => [
                [
                    'id'      => 'comment-to-dm',
                    'title'   => 'Comment to DM',
                    'line'    => 'Someone comments a keyword, they get the link in their DMs.',
                    'trigger' => 'Post or reel comment',
                    'popular' => true,
                    'to'      => '/onboarding/automation/new?trigger=comment',
                ],
                [
                    'id'      => 'story-reply',
                    'title'   => 'Story reply',
                    'line'    => 'Every reply to a story gets an answer, even at 2am.',
                    'trigger' => 'Story reply',
                    'popular' => false,
                    'to'      => '/onboarding/automation/new?trigger=story',
                ],
                [
                    'id'      => 'welcome-dm',
                    'title'   => 'Welcome DM',
                    'line'    => 'First-time messages get a greeting and your catalogue.',
                    'trigger' => 'Direct message',
                    'popular' => false,
                    'to'      => '/onboarding/automation/new?trigger=dm',
                ],
            ],
        ];

        // ── Last 7 days ───────────────────────────────────────────────
        $sevenDaysAgo = now()->subDays(7);

        // Auto-replies in last 7 days (outbound automated messages)
        $autoReplies7d = Message::whereIn(
            'conversation_id',
            Conversation::whereIn('channel_id', $channelIds)->select('id')
        )
            ->where('automated', true)
            ->where('sent_at', '>=', $sevenDaysAgo)
            ->count();

        // Posts that received comments in the last 7 days
        $engagedPostCount = Comment::whereIn('channel_id', $channelIds)
            ->where('posted_at', '>=', $sevenDaysAgo)
            ->distinct('post_id')
            ->count('post_id');

        // Most commented post caption in last 7 days
        $topPost = Comment::selectRaw('post_id, COUNT(*) as cnt')
            ->whereIn('channel_id', $channelIds)
            ->where('posted_at', '>=', $sevenDaysAgo)
            ->groupBy('post_id')
            ->orderByDesc('cnt')
            ->with('post:id,caption')
            ->first();

        $topPostCaption = $topPost?->post?->caption
            ? mb_strimwidth($topPost->post->caption, 0, 40, '…')
            : null;
        $topPostComments = $topPost?->cnt ?? 0;

        // Time saved: assume avg 2.5 min per auto-reply
        $minutesSaved = $autoReplies7d * 2.5;
        $hoursSaved   = (int) floor($minutesSaved / 60);
        $minSaved     = (int) ($minutesSaved % 60);
        $timeSavedStr = $hoursSaved > 0
            ? "{$hoursSaved}h" . ($minSaved > 0 ? " {$minSaved}m" : '')
            : "{$minSaved}m";

        $hasData = $autoReplies7d > 0 || $engagedPostCount > 0;

        $lastSeven = [
            'title'    => 'Your last 7 days',
            'has_data' => $hasData,
            'metrics'  => [
                [
                    'id'    => 'engagement',
                    'label' => 'Engagement winners',
                    'hint'  => 'Which posts pulled the most comments',
                    'value' => $hasData ? "{$engagedPostCount} " . ($engagedPostCount === 1 ? 'post' : 'posts') : null,
                    'note'  => $hasData && $topPostCaption
                        ? "«{$topPostCaption}» pulled {$topPostComments} of them."
                        : null,
                    'locked' => false,
                ],
                [
                    'id'    => 'time',
                    'label' => 'Time saved',
                    'hint'  => 'Hours you did not spend answering',
                    'value' => $hasData ? $timeSavedStr : null,
                    'note'  => $hasData ? "{$autoReplies7d} replies went out without you." : null,
                    'locked' => false,
                ],
                [
                    'id'     => 'leads',
                    'label'  => 'Leads collected',
                    'hint'   => 'Emails and numbers captured',
                    'value'  => null,
                    'note'   => null,
                    'locked' => true, // requires Pro plan — not implemented yet
                ],
            ],
        ];

        return response()->json([
            'data' => [
                'user' => [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'business_name' => $user->business_name,
                    'plan'          => 'free', // TODO: wire to billing when added
                ],
                'channels' => $channels->map(fn ($ch) => [
                    'id'              => $ch->id,
                    'platform'        => $ch->platform,
                    'username'        => $ch->username,
                    'profile_picture' => $ch->profile_picture
                        ? Storage::url($ch->profile_picture)
                        : null,
                    'status'          => $ch->status,
                    'followers'       => $ch->followers,
                ])->values(),
                'automations' => $automations->values(),
                'checklist'   => $checklist,
                'start_here'  => $startHere,
                'last_seven'  => $lastSeven,
            ],
        ]);
    }
}
