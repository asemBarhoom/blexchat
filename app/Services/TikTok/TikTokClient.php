<?php

namespace App\Services\TikTok;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;

class TikTokClient
{
    /**
     * Videos granted by TikTok's Display API `video.list` scope.
     *
     * @return list<array<string, mixed>>
     */
    public function recentVideos(Channel $channel, int $limit = 20): array
    {
        $this->refreshAccessTokenIfNeeded($channel);

        $response = Http::withToken($channel->access_token)
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://open.tiktokapis.com/v2/video/list/?'.http_build_query([
                'fields' => 'id,title,video_description,cover_image_url,share_url,create_time,like_count,comment_count',
            ]), ['max_count' => min(max($limit, 1), 20)])
            ->throw();

        return array_values(array_filter(
            (array) $response->json('data.videos', []),
            fn ($video): bool => is_array($video) && filled($video['id'] ?? null),
        ));
    }

    private function refreshAccessTokenIfNeeded(Channel $channel): void
    {
        if (! $channel->token_expires_at?->isPast() || blank($channel->refresh_token)) {
            return;
        }

        $token = Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => config('services.tiktok.client_id'),
                'client_secret' => config('services.tiktok.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $channel->refresh_token,
            ])->throw()->json();

        $channel->update([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $channel->refresh_token,
            'token_expires_at' => now()->addSeconds((int) $token['expires_in']),
            'status' => 'active',
        ]);
        $channel->refresh();
    }
}
