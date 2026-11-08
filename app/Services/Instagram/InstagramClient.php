<?php

namespace App\Services\Instagram;

use App\Models\Channel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Instagram API with Instagram Login: account connection, comment replies and DMs.
 * Reads are retried on transient failures; sends never are, so a customer cannot get a reply twice.
 */
class InstagramClient
{
    private const SCOPES = ['instagram_business_basic', 'instagram_business_manage_messages', 'instagram_business_manage_comments'];

    public function authorizationUrl(string $state): string
    {
        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => config('services.instagram.app_id'),
            'redirect_uri' => config('services.instagram.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
            'state' => $state,
        ]);
    }

    /**
     * Trades the code Instagram sent back to the redirect page for a long-lived token (about 60 days).
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function exchangeCode(string $code): array
    {
        $shortLived = Http::asForm()->connectTimeout(5)->timeout(15)
            ->post('https://api.instagram.com/oauth/access_token', [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'code' => $code,
            ])
            ->throw()
            ->json('access_token');

        return $this->read()->get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('services.instagram.app_secret'),
            'access_token' => $shortLived,
        ])->throw()->json();
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshToken(string $token): array
    {
        return $this->read()->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ])->throw()->json();
    }

    /**
     * `user_id` is the professional account id that webhooks report as the entry id. `account_type`
     * is BUSINESS or MEDIA_CREATOR for the professional accounts that can connect.
     *
     * @return array{user_id: string, username: string, account_type?: string, profile_picture_url?: string, followers_count?: int}
     */
    public function profile(string $token): array
    {
        return $this->read($token)->get('/me', [
            'fields' => 'user_id,username,account_type,profile_picture_url,followers_count',
        ])->throw()->json();
    }

    /**
     * @return array{caption?: string, media_product_type?: string, media_type?: string, timestamp?: string, thumbnail_url?: string, media_url?: string, like_count?: int, comments_count?: int}
     */
    public function media(Channel $channel, string $mediaId): array
    {
        return $this->read($channel->access_token)->get("/{$mediaId}", [
            'fields' => 'caption,media_type,media_product_type,timestamp,thumbnail_url,media_url,like_count,comments_count',
        ])->throw()->json();
    }

    /**
     * The account's latest posts and reels, newest first.
     *
     * @return list<array{id: string, caption?: string, media_product_type?: string, media_type?: string, timestamp?: string, thumbnail_url?: string, media_url?: string, like_count?: int, comments_count?: int}>
     */
    public function recentMedia(Channel $channel, int $limit = 50): array
    {
        return $this->read($channel->access_token)->get('/me/media', [
            'fields' => 'id,caption,media_type,media_product_type,timestamp,thumbnail_url,media_url,like_count,comments_count',
            'limit' => $limit,
        ])->throw()->json('data') ?? [];
    }

    /**
     * The person behind an Instagram-scoped id, for naming a new conversation.
     *
     * @return array{username?: string, name?: string, profile_pic?: string}
     */
    public function contact(Channel $channel, string $instagramScopedId): array
    {
        return $this->read($channel->access_token)->get("/{$instagramScopedId}", [
            'fields' => 'username,name,profile_pic',
        ])->throw()->json();
    }

    public function subscribeToWebhooks(string $token): void
    {
        $this->graph($token)->post('/me/subscribed_apps?subscribed_fields=comments,messages')->throw();
    }

    public function replyToComment(Channel $channel, string $commentId, string $text): ?string
    {
        return $this->graph($channel->access_token)
            ->post("/{$commentId}/replies", ['message' => $text])
            ->throw()
            ->json('id');
    }

    /**
     * A DM to the person who wrote a comment, allowed once per comment within 7 days.
     *
     * @param  array<string, mixed>  $message
     */
    public function sendPrivateReply(Channel $channel, string $commentId, array $message): ?string
    {
        return $this->sendDm($channel, ['comment_id' => $commentId], $message);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function sendMessage(Channel $channel, string $recipientId, array $message): ?string
    {
        return $this->sendDm($channel, ['id' => $recipientId], $message);
    }

    /**
     * Instagram's reason for a refusal. The Graph API answers with `error.message`; the OAuth
     * endpoint uses `error_message` or, for some failures, a plain-text body.
     */
    public static function errorMessage(RequestException $exception): ?string
    {
        $response = $exception->response;

        return $response->json('error.message')
            ?? $response->json('error_message')
            ?? (str($response->body())->trim()->limit(200)->toString() ?: null);
    }

    /**
     * A text DM, or a text with one link button when both label and URL are given.
     *
     * @return array<string, mixed>
     */
    public static function message(string $text, ?string $buttonLabel = null, ?string $buttonUrl = null): array
    {
        if (blank($buttonLabel) || blank($buttonUrl)) {
            return ['text' => $text];
        }

        return ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'button',
            'text' => $text,
            'buttons' => [['type' => 'web_url', 'url' => $buttonUrl, 'title' => $buttonLabel]],
        ]]];
    }

    /**
     * A text DM with one quick-reply chip; tapping it sends the chip's title back as their message.
     *
     * @return array<string, mixed>
     */
    public static function withQuickReply(string $text, string $title, string $payload): array
    {
        return ['text' => $text, 'quick_replies' => [['content_type' => 'text', 'title' => $title, 'payload' => $payload]]];
    }

    /**
     * @param  array<string, string>  $recipient
     * @param  array<string, mixed>  $message
     */
    private function sendDm(Channel $channel, array $recipient, array $message): ?string
    {
        return $this->graph($channel->access_token)
            ->post("/{$channel->account_id}/messages", ['recipient' => $recipient, 'message' => $message])
            ->throw()
            ->json('message_id');
    }

    private function read(?string $token = null): PendingRequest
    {
        return $this->graph($token)->retry([200, 1000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)));
    }

    private function graph(?string $token): PendingRequest
    {
        $request = Http::baseUrl('https://graph.instagram.com/'.config('services.instagram.graph_version'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);

        return $token === null ? $request : $request->withToken($token);
    }
}
