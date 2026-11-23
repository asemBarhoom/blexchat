<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostCollection;
use App\Models\Channel;
use App\Models\Post;
use App\Services\Channels\DeliveryFailure;
use App\Services\Instagram\InstagramClient;
use App\Services\TikTok\TikTokClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ChannelPostController extends Controller
{
    public function __construct(private InstagramClient $instagram, private TikTokClient $tiktok) {}

    #[OA\Get(
        path: '/api/v1/channels/{channel}/posts',
        summary: 'The account’s posts and reels, for “Pick a post” (W11)',
        description: 'Newest first. An active Instagram channel is refreshed from Instagram first; when that fails the posts already known are returned and `meta.synced` is false. When Instagram refused the token itself the channel turns `expired` and `meta.needs_reconnect` is true: show “Reconnect” instead of “Try again”. An empty list is the “No posts yet” state.',
        security: [['sanctum' => []]],
        tags: ['Automation wizard'],
        parameters: [
            new OA\Parameter(name: 'channel', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The posts', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer', description: 'Send it as `post_id`'),
                    new OA\Property(property: 'platform_post_id', type: 'string'),
                    new OA\Property(property: 'caption', type: 'string', nullable: true),
                    new OA\Property(property: 'thumbnail_url', type: 'string', nullable: true),
                    new OA\Property(property: 'permalink', type: 'string', format: 'uri', nullable: true, description: 'The post on Instagram'),
                    new OA\Property(property: 'media_type', type: 'string', nullable: true, enum: ['image', 'video', 'carousel', 'reel']),
                    new OA\Property(property: 'likes', type: 'integer', nullable: true, example: 1204, description: 'Like count, for the phone preview'),
                    new OA\Property(property: 'posted_at', type: 'string', format: 'date-time', nullable: true),
                ])),
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'per_page', type: 'integer'),
                    new OA\Property(property: 'synced', type: 'boolean', description: 'Whether Instagram was reached just now'),
                    new OA\Property(property: 'needs_reconnect', type: 'boolean', description: 'The channel must be connected again before it can sync'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another account’s channel'),
        ],
    )]
    public function __invoke(Request $request, Channel $channel): PostCollection
    {
        abort_unless($request->user()->workspace()->holds($channel), 403);

        $synced = $this->sync($channel);
        $perPage = min(max($request->integer('per_page', 30), 1), 100);

        return new PostCollection(
            Post::where('channel_id', $channel->id)->orderByDesc('posted_at')->orderByDesc('id')->paginate($perPage),
            $synced,
            $channel->needsReconnect(),
        );
    }

    /**
     * Brings in the latest posts from Instagram; false when the channel cannot be asked or refused.
     */
    private function sync(Channel $channel): bool
    {
        if (! in_array($channel->platform, ['instagram', 'tiktok'], true) || $channel->status !== 'active') {
            return false;
        }

        try {
            $mediaItems = $channel->platform === 'tiktok'
                ? $this->tiktok->recentVideos($channel)
                : $this->instagram->recentMedia($channel);

            foreach ($mediaItems as $media) {
                Post::updateOrCreate(
                    ['channel_id' => $channel->id, 'platform_post_id' => $media['id']],
                    $channel->platform === 'tiktok' ? Post::attributesFromTikTok($media) : Post::attributesFromInstagram($media),
                );
            }

            return true;
        } catch (RequestException|ConnectionException $exception) {
            Log::warning(ucfirst($channel->platform).' posts sync failed', [
                'channel_id' => $channel->id,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
                'error' => $exception instanceof RequestException && $channel->platform === 'instagram' ? InstagramClient::errorMessage($exception) : $exception->getMessage(),
            ]);

            if (DeliveryFailure::isTokenRejected($exception)) {
                $channel->update(['status' => 'expired']);
            }

            return false;
        }
    }
}
