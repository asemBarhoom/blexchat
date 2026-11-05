<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AutomationStats', description: 'Every figure is null until the automation has answered someone ("No data yet"), which is not the same as zero.', properties: [
    new OA\Property(property: 'sends', type: 'integer', nullable: true, example: 148, description: 'DMs it has sent'),
    new OA\Property(property: 'public_replies', type: 'integer', nullable: true, example: 120, description: 'Comments it answered publicly'),
    new OA\Property(property: 'clicks', type: 'integer', nullable: true, example: 37, description: 'DMs whose button was clicked, each counted once'),
    new OA\Property(property: 'ctr', type: 'number', nullable: true, minimum: 0, maximum: 1, example: 0.25, description: 'clicks ÷ DMs sent with a button; 0 while none had a button, null only before it has answered anyone'),
    new OA\Property(property: 'emails', type: 'integer', nullable: true, example: 9, description: 'Addresses collected from the people it brought in; null unless its email question (ask_email) is on'),
    new OA\Property(property: 'last_run_at', type: 'string', format: 'date-time', nullable: true),
])]
#[OA\Schema(schema: 'OverviewStats', description: 'Home’s "last 7 days" cards. Each card is null until the account has had anything of its kind; after that its figures are numbers, zero included. `previous` is the same figure for the 7 days before.', properties: [
    new OA\Property(property: 'from', type: 'string', format: 'date-time'),
    new OA\Property(property: 'to', type: 'string', format: 'date-time'),
    new OA\Property(property: 'engagement', type: 'object', nullable: true, description: 'Engagement winners: comments on the connected accounts’ posts', properties: [
        new OA\Property(property: 'comments', type: 'integer', example: 214),
        new OA\Property(property: 'previous', type: 'integer', example: 180),
        new OA\Property(property: 'top_posts', type: 'array', description: 'Up to 3, most comments first', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'caption', type: 'string', nullable: true),
            new OA\Property(property: 'thumbnail_url', type: 'string', nullable: true),
            new OA\Property(property: 'media_type', type: 'string', nullable: true),
            new OA\Property(property: 'comments', type: 'integer', example: 61),
        ])),
    ]),
    new OA\Property(property: 'time_saved', type: 'object', nullable: true, description: 'Replies the automations sent instead of the owner', properties: [
        new OA\Property(property: 'replies', type: 'integer', example: 148, description: 'Automatic DMs and public comment replies'),
        new OA\Property(property: 'minutes', type: 'integer', example: 148, description: 'An estimate: one minute per reply'),
        new OA\Property(property: 'previous', type: 'integer', example: 96, description: 'Replies the 7 days before'),
    ]),
    new OA\Property(property: 'leads', type: 'object', nullable: true, description: 'New contacts who gave an email or a phone number (not those added by hand). Email capture is a paid option; the front end locks this card on plans without it', properties: [
        new OA\Property(property: 'contacts', type: 'integer', example: 9),
        new OA\Property(property: 'previous', type: 'integer', example: 4),
    ]),
])]
class StatsController extends Controller
{
    /** How long one reply would have taken by hand, for "time saved". */
    public const SECONDS_SAVED_PER_REPLY = 60;

    public const WINDOW_DAYS = 7;

    #[OA\Get(
        path: '/api/v1/automations/{id}/stats',
        summary: 'One automation’s figures since it went live (W17)',
        description: 'The four figures on the automation’s page. A DM button’s link goes through this API, which counts the click before sending the customer on.',
        security: [['sanctum' => []]],
        tags: ['Automations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AutomationStats'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another owner’s automation'),
            new OA\Response(response: 404, description: 'No such automation, or it is in the Trash'),
        ],
    )]
    public function automation(Request $request, Automation $automation): JsonResponse
    {
        abort_unless($automation->user_id === $request->user()->id, 403);

        $messages = Message::query()->where('automation_id', $automation->id)->where('automated', true);
        $publicReplies = Comment::query()->where('automation_id', $automation->id)->where('automated_reply', true)->count();
        $sends = (clone $messages)->count();

        if ($sends === 0 && $publicReplies === 0) {
            return response()->json(['data' => array_fill_keys(['sends', 'public_replies', 'clicks', 'ctr', 'emails', 'last_run_at'], null)]);
        }

        $withButton = (clone $messages)->whereNotNull('link_token')->count();
        $clicks = (clone $messages)->whereNotNull('first_clicked_at')->count();
        $lastRunAt = max((clone $messages)->max('sent_at'), Comment::where('automation_id', $automation->id)->max('updated_at'));

        return response()->json(['data' => [
            'sends' => $sends,
            'public_replies' => $publicReplies,
            'clicks' => $clicks,
            'ctr' => $withButton > 0 ? round($clicks / $withButton, 4) : 0.0,
            'emails' => ($automation->extras['ask_email'] ?? false)
                ? Contact::where('source_automation_id', $automation->id)->whereNotNull('email')->count()
                : null,
            'last_run_at' => $lastRunAt ? Carbon::parse($lastRunAt)->toIso8601String() : null,
        ]]);
    }

    #[OA\Get(
        path: '/api/v1/stats/overview',
        summary: 'Home’s "last 7 days" cards (S7.2)',
        security: [['sanctum' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/OverviewStats'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $to = now();
        $from = $to->copy()->subDays(self::WINDOW_DAYS);
        $before = $from->copy()->subDays(self::WINDOW_DAYS);

        return response()->json(['data' => [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'engagement' => $this->engagement($user, $before, $from, $to),
            'time_saved' => $this->timeSaved($user, $before, $from, $to),
            'leads' => $this->leads($user, $before, $from, $to),
        ]]);
    }

    /**
     * @return array{comments: int, previous: int, top_posts: array<int, array<string, mixed>>}|null
     */
    private function engagement(User $user, Carbon $before, Carbon $from, Carbon $to): ?array
    {
        $comments = fn (): Builder => Comment::query()->whereIn('comments.channel_id', $user->channels()->select('id'));

        if (! $comments()->exists()) {
            return null;
        }

        $at = DB::raw('COALESCE(comments.posted_at, comments.created_at)');
        $topPosts = $comments()
            ->join('posts', 'posts.id', '=', 'comments.post_id')
            ->whereBetween($at, [$from, $to])
            ->groupBy('posts.id', 'posts.caption', 'posts.thumbnail_url', 'posts.media_type')
            ->orderByDesc('comments_count')
            ->orderByDesc('posts.id')
            ->limit(3)
            ->get(['posts.id', 'posts.caption', 'posts.thumbnail_url', 'posts.media_type', DB::raw('COUNT(*) as comments_count')]);

        return [
            'comments' => $comments()->whereBetween($at, [$from, $to])->count(),
            'previous' => $comments()->where($at, '>=', $before)->where($at, '<', $from)->count(),
            'top_posts' => $topPosts->map(fn (Comment $row): array => [
                'id' => $row->id,
                'caption' => $row->caption,
                'thumbnail_url' => $row->thumbnail_url,
                'media_type' => $row->media_type,
                'comments' => (int) $row->comments_count,
            ])->all(),
        ];
    }

    /**
     * @return array{replies: int, minutes: int, previous: int}|null
     */
    private function timeSaved(User $user, Carbon $before, Carbon $from, Carbon $to): ?array
    {
        $automationIds = $user->automations()->withTrashed()->select('id');
        $dms = fn (): Builder => Message::query()->where('automated', true)->whereIn('automation_id', $automationIds);
        $publicReplies = fn (): Builder => Comment::query()->where('automated_reply', true)->whereIn('automation_id', $automationIds);

        if (! $dms()->exists() && ! $publicReplies()->exists()) {
            return null;
        }

        $replies = $dms()->whereBetween('sent_at', [$from, $to])->count() + $publicReplies()->whereBetween('updated_at', [$from, $to])->count();
        $previous = $dms()->where('sent_at', '>=', $before)->where('sent_at', '<', $from)->count()
            + $publicReplies()->where('updated_at', '>=', $before)->where('updated_at', '<', $from)->count();

        return [
            'replies' => $replies,
            'minutes' => intdiv($replies * self::SECONDS_SAVED_PER_REPLY, 60),
            'previous' => $previous,
        ];
    }

    /**
     * @return array{contacts: int, previous: int}|null
     */
    private function leads(User $user, Carbon $before, Carbon $from, Carbon $to): ?array
    {
        // Contacts added by hand were not collected.
        $leads = fn (): Builder => Contact::query()->where('user_id', $user->id)->where('added_manually', false)
            ->where(fn (Builder $query) => $query->whereNotNull('email')->orWhereNotNull('phone'));

        if (! $leads()->exists()) {
            return null;
        }

        return [
            'contacts' => $leads()->whereBetween('created_at', [$from, $to])->count(),
            'previous' => $leads()->where('created_at', '>=', $before)->where('created_at', '<', $from)->count(),
        ];
    }
}
