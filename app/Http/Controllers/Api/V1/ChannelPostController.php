<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostCollection;
use App\Models\Channel;
use App\Models\Post;
use App\Services\Instagram\InstagramClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChannelPostController extends Controller
{
    public function __construct(private InstagramClient $instagram) {}

    #[OA\Get(
        path: '/api/v1/channels/{channel}/posts',
        summary: 'The account’s posts and reels, for “Pick a post” (W11)',
        description: 'Newest first. An active Instagram channel is refreshed from Instagram first; when that fails the posts already known are returned and `meta.synced` is false. An empty list is the “No posts yet” state.',
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
                    new OA\Property(property: 'media_type', type: 'string', nullable: true, enum: ['image', 'video', 'carousel', 'reel']),
                    new OA\Property(property: 'likes', type: 'integer', nullable: true, example: 1204, description: 'Like count, for the phone preview'),
                    new OA\Property(property: 'posted_at', type: 'string', format: 'date-time', nullable: true),
                ])),
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'per_page', type: 'integer'),
                    new OA\Property(property: 'synced', type: 'boolean', description: 'Whether Instagram was reached just now'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 403, description: 'Another account’s channel'),
        ],
    )]
    public function __invoke(Request $request, Channel $channel): PostCollection
    {
        abort_unless($channel->user_id === $request->user()->id, 403);

        $synced = $this->sync($channel);
        $perPage = min(max($request->integer('per_page', 30), 1), 100);

        return new PostCollection(
            Post::where('channel_id', $channel->id)->orderByDesc('posted_at')->orderByDesc('id')->paginate($perPage),
            $synced,
        );
    }

    /**
     * Brings in the latest posts from Instagram; false when the channel cannot be asked or refused.
     */
    private function sync(Channel $channel): bool
    {
        if ($channel->platform !== 'instagram' || $channel->status !== 'active') {
            return false;
        }

        try {
            foreach ($this->instagram->recentMedia($channel) as $media) {
                Post::updateOrCreate(
                    ['channel_id' => $channel->id, 'platform_post_id' => $media['id']],
                    Post::attributesFromInstagram($media),
                );
            }

            return true;
        } catch (RequestException|ConnectionException) {
            return false;
        }
    }
}
