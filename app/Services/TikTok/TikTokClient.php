<?php

namespace App\Services\TikTok;

use App\Models\Channel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok API for Business (v1.3) on behalf of a connected TikTok account: its tokens and profile,
 * its posts, direct messages (Business Messaging API) and replies to comments (Accounts API).
 * Every answer is an envelope of `code`, `message` and `data`: a code other than 0 is a failure,
 * even on HTTP 200, and is thrown as a RequestException like any other refusal.
 */
class TikTokClient
{
    public const BASE_URL = 'https://business-api.tiktok.com/open_api/v1.3/';

    /** The webhook event types the app subscribes to. */
    public const WEBHOOK_EVENT_TYPES = ['DIRECT_MESSAGE', 'COMMENT'];

    /** How old a signed webhook may be, in seconds, before it is refused as a replay. */
    public const WEBHOOK_TOLERANCE_SECONDS = 300;

    /** A token this close to its end (it lasts a day) is renewed before it is used. */
    private const REFRESH_MARGIN_MINUTES = 10;

    /**
     * Trades the code from the authorization redirect for the account's tokens:
     * access_token (a day), refresh_token (a year), expires_in, open_id and scope.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException|ConnectionException
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->data($this->http()->post('tt_user/oauth2/token/', [
            'client_id' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'authorization_code',
            'auth_code' => $code,
            'redirect_uri' => $redirectUri,
        ]));
    }

    /**
     * The account's names and picture, and whether it may use the Business Messaging API:
     * only an account with Advanced Access or a Verified Business Account may.
     *
     * @return array{username?: string, display_name?: string, profile_image?: string, has_advanced_access?: bool}
     *
     * @throws RequestException|ConnectionException
     */
    public function profile(string $accessToken, string $businessId): array
    {
        return $this->data($this->http($accessToken)->get('business/get/', [
            'business_id' => $businessId,
            'fields' => json_encode(['username', 'display_name', 'profile_image', 'has_advanced_access']),
        ]));
    }

    /**
     * The account's latest posts, each with its `item_id` also as `id`.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RequestException|ConnectionException
     */
    public function recentVideos(Channel $channel, int $limit = 20): array
    {
        $videos = $this->data($this->http($this->token($channel))->get('business/video/list/', [
            'business_id' => $channel->account_id,
            'fields' => json_encode(['item_id', 'caption', 'thumbnail_url', 'share_url', 'likes', 'comments', 'create_time']),
            'max_count' => min(max($limit, 1), 20),
        ]))['videos'] ?? [];

        $videos = array_filter((array) $videos, fn (mixed $video): bool => is_array($video) && filled($video['item_id'] ?? null));

        return array_values(array_map(fn (array $video): array => ['id' => (string) $video['item_id'], ...$video], $videos));
    }

    /**
     * A text message to a conversation the person started; TikTok allows up to 10 in the 48 hours after each of their messages.
     *
     * @throws RequestException|ConnectionException
     */
    public function sendText(Channel $channel, string $conversationId, string $text): ?string
    {
        $data = $this->data($this->http($this->token($channel))->post('business/message/send/', [
            'business_id' => $channel->account_id,
            'recipient_type' => 'CONVERSATION',
            'recipient' => $conversationId,
            'message_type' => 'TEXT',
            'text' => ['body' => $text],
        ]));

        return isset($data['message']['message_id']) ? (string) $data['message']['message_id'] : null;
    }

    /**
     * A public reply under a comment on one of the account's posts.
     *
     * @throws RequestException|ConnectionException
     */
    public function replyToComment(Channel $channel, string $videoId, string $commentId, string $text): ?string
    {
        $data = $this->data($this->http($this->token($channel))->post('business/comment/reply/create/', [
            'business_id' => $channel->account_id,
            'video_id' => $videoId,
            'comment_id' => $commentId,
            'text' => $text,
        ]));

        return isset($data['comment_id']) ? (string) $data['comment_id'] : null;
    }

    /**
     * Points the app's webhook for one event type at the callback URL. It is app-wide: done once,
     * not per account.
     *
     * @throws RequestException|ConnectionException
     */
    public function subscribeWebhook(string $eventType, string $callbackUrl): void
    {
        $this->data($this->http()->post('business/webhook/update/', [
            'app_id' => config('services.tiktok.client_id'),
            'secret' => config('services.tiktok.client_secret'),
            'event_type' => $eventType,
            'callback_url' => $callbackUrl,
        ]));
    }

    /**
     * Whether a webhook body was signed by TikTok: the `TikTok-Signature` header holds `t=<timestamp>,s=<signature>`,
     * the signature being the hex HMAC-SHA256 of "<timestamp>.<body>" with the app's secret. A signature
     * older than the tolerance is refused, so a captured request cannot be replayed.
     */
    public static function validSignature(?string $header, string $body): bool
    {
        $secret = (string) config('services.tiktok.client_secret');
        $parts = [];

        foreach (explode(',', (string) $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $signature = (string) ($parts['s'] ?? '');

        if ($secret === '' || $timestamp === 0 || $signature === '' || abs(now()->timestamp - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature);
    }

    /**
     * The channel's access token, renewed first when it is about to end.
     *
     * @throws RequestException|ConnectionException
     */
    private function token(Channel $channel): string
    {
        $endsSoon = $channel->token_expires_at !== null && $channel->token_expires_at->lte(now()->addMinutes(self::REFRESH_MARGIN_MINUTES));

        if (! $endsSoon || blank($channel->refresh_token)) {
            return (string) $channel->access_token;
        }

        $token = $this->data($this->http()->post('tt_user/oauth2/refresh_token/', [
            'client_id' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $channel->refresh_token,
        ]));

        $channel->update([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $channel->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            'status' => 'active',
        ]);

        return (string) $token['access_token'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    private function data(Response $response): array
    {
        $response->throw();

        if ((int) $response->json('code', -1) !== 0) {
            throw new RequestException($response);
        }

        return (array) $response->json('data', []);
    }

    private function http(?string $accessToken = null): PendingRequest
    {
        $request = Http::baseUrl(self::BASE_URL)->acceptJson()->asJson()->connectTimeout(5)->timeout(15);

        return $accessToken === null ? $request : $request->withHeaders(['Access-Token' => $accessToken]);
    }
}
