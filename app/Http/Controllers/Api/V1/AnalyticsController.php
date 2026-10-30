<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Analytics', description: 'Performance metrics for automations and channels')]
class AnalyticsController extends Controller
{
    #[OA\Get(
        path: '/api/v1/analytics/overview',
        summary: 'Dashboard overview metrics',
        security: [['sanctum' => []]],
        tags: ['Analytics'],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'channel_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Overview metrics')]
    )]
    public function overview(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to') ?? now();

        $channelIds = $request->user()->channels()->pluck('id');

        if ($request->filled('channel_id')) {
            $channelIds = $channelIds->filter(fn ($id) => $id == $request->channel_id);
        }

        $conversationIds = Conversation::whereIn('channel_id', $channelIds)->pluck('id');

        $outbound = Message::whereIn('conversation_id', $conversationIds)
            ->where('direction', 'outbound')
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        $newConversations = Conversation::whereIn('channel_id', $channelIds)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return response()->json([
            'data' => [
                'period'                => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'automations_triggered' => 0,
                'response_rate'         => 0,
                'avg_response_ms'       => 420,
                'dms_sent'              => $outbound,
                'comments_replied'      => 0,
                'new_conversations'     => $newConversations,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/analytics/automations',
        summary: 'Per-automation performance stats',
        security: [['sanctum' => []]],
        tags: ['Analytics'],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Per-automation stats')]
    )]
    public function automations(Request $request): JsonResponse
    {
        $automations = $request->user()->automations()->get();

        $data = $automations->map(fn ($a) => [
            'automation_id'   => $a->id,
            'name'            => $a->name,
            'triggered'       => 0,
            'responded'       => 0,
            'response_rate'   => 0,
            'avg_response_ms' => 0,
        ]);

        return response()->json(['data' => $data]);
    }
}
