<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard', description: 'Signed-in app home screen')]
class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/v1/dashboard',
        summary: 'App home screen — one request, all data',
        description: 'Everything the signed-in home screen shows: the user, connected channels, automations, first-steps checklist, automation templates and last-7-day metrics.',
        security: [['sanctum' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard payload', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'user', type: 'object', properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string', example: 'Nour'),
                        new OA\Property(property: 'business_name', type: 'string', example: 'Nour Boutique'),
                        new OA\Property(property: 'plan', type: 'string', enum: ['free', 'essential', 'pro']),
                    ]),
                    new OA\Property(property: 'channels', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'platform', type: 'string', enum: ['instagram', 'whatsapp', 'tiktok', 'messenger']),
                        new OA\Property(property: 'username', type: 'string', nullable: true),
                        new OA\Property(property: 'profile_picture', type: 'string', format: 'uri', nullable: true),
                        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'expired']),
                        new OA\Property(property: 'followers', type: 'integer'),
                    ])),
                    new OA\Property(property: 'automations', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'status', type: 'string', enum: ['live', 'stopped']),
                        new OA\Property(property: 'trigger_type', type: 'string', enum: ['comment_keyword', 'dm_keyword', 'story_mention']),
                        new OA\Property(property: 'runs', type: 'integer', description: 'Automated messages this automation has sent'),
                    ])),
                    new OA\Property(property: 'checklist', type: 'object', properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'steps', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'string', enum: ['connect', 'automation', 'test']),
                            new OA\Property(property: 'label', type: 'string'),
                            new OA\Property(property: 'done', type: 'boolean'),
                            new OA\Property(property: 'to', type: 'string'),
                        ])),
                    ]),
                    new OA\Property(property: 'start_here', type: 'object', properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'lead', type: 'string'),
                        new OA\Property(property: 'templates', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'line', type: 'string'),
                            new OA\Property(property: 'trigger', type: 'string'),
                            new OA\Property(property: 'popular', type: 'boolean'),
                            new OA\Property(property: 'to', type: 'string'),
                        ])),
                    ]),
                    new OA\Property(property: 'last_seven', type: 'object', properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'has_data', type: 'boolean'),
                        new OA\Property(property: 'metrics', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'string', enum: ['engagement', 'time', 'leads']),
                            new OA\Property(property: 'label', type: 'string'),
                            new OA\Property(property: 'hint', type: 'string'),
                            new OA\Property(property: 'value', type: 'string', nullable: true),
                            new OA\Property(property: 'note', type: 'string', nullable: true),
                            new OA\Property(property: 'locked', type: 'boolean'),
                        ])),
                    ]),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $channels = $user->channels()->orderByDesc('connected_at')->get();
        $channelIds = $channels->pluck('id');
        $automationModels = $user->automations()->orderByDesc('active')->orderByDesc('created_at')->get();

        $runsByAutomation = Message::query()
            ->whereIn('automation_id', $automationModels->pluck('id'))
            ->selectRaw('automation_id, COUNT(*) as runs')
            ->groupBy('automation_id')
            ->pluck('runs', 'automation_id');

        $automations = $automationModels->map(fn ($automation): array => [
            'id' => $automation->id,
            'name' => $automation->name,
            'status' => $automation->active ? 'live' : 'stopped',
            'trigger_type' => $automation->trigger_type,
            'runs' => (int) ($runsByAutomation[$automation->id] ?? 0),
        ]);

        return response()->json(['data' => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'business_name' => $user->business_name,
                'plan' => 'free',
            ],
            'channels' => $channels->map(fn ($channel): array => [
                'id' => $channel->id,
                'platform' => $channel->platform,
                'username' => $channel->username,
                'profile_picture' => $this->imageUrl($channel->profile_picture),
                'status' => $channel->status,
                'followers' => $channel->followers,
            ])->values(),
            'automations' => $automations->values(),
            'checklist' => $this->checklist($channels->isNotEmpty(), $automations->isNotEmpty()),
            'start_here' => $this->startHere(),
            'last_seven' => $this->lastSeven($channelIds->all()),
        ]]);
    }

    /**
     * Platform avatars arrive as full URLs; seeded ones are paths on the public disk.
     */
    private function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : url(Storage::url($path));
    }

    /**
     * @return array{title: string, steps: list<array{id: string, label: string, done: bool, to: string}>}
     */
    private function checklist(bool $hasChannel, bool $hasAutomation): array
    {
        return [
            'title' => 'Your first steps',
            'steps' => [
                ['id' => 'connect', 'label' => 'Connect a channel', 'done' => $hasChannel, 'to' => '/onboarding/channel'],
                ['id' => 'automation', 'label' => 'Build your first automation', 'done' => $hasAutomation, 'to' => '/onboarding/automation/new?trigger=comment'],
                // Opening the preview is tracked client-side, so the server never marks it done.
                ['id' => 'test', 'label' => 'Send yourself a test comment', 'done' => false, 'to' => '/app/automation'],
            ],
        ];
    }

    /**
     * @return array{title: string, lead: string, templates: list<array<string, mixed>>}
     */
    private function startHere(): array
    {
        return [
            'title' => 'Start here',
            'lead' => 'Three moves that pay for themselves in a week.',
            'templates' => [
                ['id' => 'comment-to-dm', 'title' => 'Comment to DM', 'line' => 'Someone comments a keyword, they get the link in their DMs.', 'trigger' => 'Post or reel comment', 'popular' => true, 'to' => '/onboarding/automation/new?trigger=comment'],
                ['id' => 'story-reply', 'title' => 'Story reply', 'line' => 'Every reply to a story gets an answer, even at 2am.', 'trigger' => 'Story reply', 'popular' => false, 'to' => '/onboarding/automation/new?trigger=story'],
                ['id' => 'welcome-dm', 'title' => 'Welcome DM', 'line' => 'First-time messages get a greeting and your catalogue.', 'trigger' => 'Direct message', 'popular' => false, 'to' => '/onboarding/automation/new?trigger=dm'],
            ],
        ];
    }

    /**
     * @param  list<int>  $channelIds
     * @return array{title: string, has_data: bool, metrics: list<array<string, mixed>>}
     */
    private function lastSeven(array $channelIds): array
    {
        $since = now()->subDays(7);

        $autoReplies = Message::query()
            ->whereIn('conversation_id', Conversation::query()->whereIn('channel_id', $channelIds)->select('id'))
            ->where('automated', true)
            ->where('sent_at', '>=', $since)
            ->count();

        $engagedPosts = Comment::query()
            ->whereIn('channel_id', $channelIds)
            ->where('posted_at', '>=', $since)
            ->distinct()
            ->count('post_id');

        $topPost = Comment::query()
            ->whereIn('channel_id', $channelIds)
            ->where('posted_at', '>=', $since)
            ->selectRaw('post_id, COUNT(*) as comments')
            ->groupBy('post_id')
            ->orderByDesc('comments')
            ->with('post:id,caption')
            ->first();

        $hasData = $autoReplies > 0 || $engagedPosts > 0;
        $minutesSaved = (int) round($autoReplies * 2.5);
        $timeSaved = intdiv($minutesSaved, 60) > 0
            ? intdiv($minutesSaved, 60).'h'.($minutesSaved % 60 > 0 ? ' '.($minutesSaved % 60).'m' : '')
            : $minutesSaved.'m';
        $topCaption = $topPost?->post?->caption ? mb_strimwidth($topPost->post->caption, 0, 40, '…') : null;

        return [
            'title' => 'Your last 7 days',
            'has_data' => $hasData,
            'metrics' => [
                [
                    'id' => 'engagement',
                    'label' => 'Engagement winners',
                    'hint' => 'Which posts pulled the most comments',
                    'value' => $hasData ? $engagedPosts.' '.($engagedPosts === 1 ? 'post' : 'posts') : null,
                    'note' => $hasData && $topCaption ? "«{$topCaption}» pulled {$topPost->comments} of them." : null,
                    'locked' => false,
                ],
                [
                    'id' => 'time',
                    'label' => 'Time saved',
                    'hint' => 'Hours you did not spend answering',
                    'value' => $hasData ? $timeSaved : null,
                    'note' => $hasData ? "{$autoReplies} replies went out without you." : null,
                    'locked' => false,
                ],
                [
                    'id' => 'leads',
                    'label' => 'Leads collected',
                    'hint' => 'Emails and numbers captured',
                    'value' => null,
                    'note' => null,
                    'locked' => true,
                ],
            ],
        ];
    }
}
