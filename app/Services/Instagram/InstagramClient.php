<?php

namespace App\Services\Instagram;

use App\Models\Channel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Instagram API with Instagram Login, or with Unified Login (Facebook Login for Business) when a
 * configuration id is set, as newer Meta apps require: account connection, comment replies and DMs.
 * Reads are retried on transient failures; sends never are, so a customer cannot get a reply twice.
 */
class InstagramClient
{
    /** How long a Unified Login token is treated as valid; Meta issues it without an end date. */
    private const UNIFIED_TOKEN_SECONDS = 315360000;

    private const SCOPES = ['instagram_business_basic', 'instagram_business_manage_messages', 'instagram_business_manage_comments'];

    public function authorizationUrl(string $state): string
    {
        if (self::usesUnifiedLogin()) {
            return 'https://www.facebook.com/dialog/oauth?'.http_build_query([
                'client_id' => config('services.instagram.app_id'),
                'config_id' => config('services.instagram.config_id'),
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'response_type' => 'code',
                'state' => $state,
            ]);
        }

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
        if (self::usesUnifiedLogin()) {
            $token = $this->read()->get('/oauth/access_token', [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'code' => $code,
            ])->throw()->json();

            return ['access_token' => $token['access_token'], 'expires_in' => (int) ($token['expires_in'] ?? self::UNIFIED_TOKEN_SECONDS)];
        }

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
        // A Unified Login token is a business system user token that does not expire on its own.
        if (self::usesUnifiedLogin()) {
            return ['access_token' => $token, 'expires_in' => self::UNIFIED_TOKEN_SECONDS];
        }

        return $this->read()->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ])->throw()->json();
    }

    /**
     * `user_id` is the professional account id that webhooks report as the entry id. `account_type`
     * is BUSINESS or MEDIA_CREATOR for the professional accounts that can connect.
     *
     * `$connectedAccountIds`: the person's Instagram accounts already connected, so that, among several
     * shared accounts Meta's grants cannot tell apart, a new one is connected rather than one they have.
     *
     * @param  list<string>  $connectedAccountIds
     * @return array{user_id: string, username: string, meta_user_id?: string|null, account_type?: string, profile_picture_url?: string, followers_count?: int}
     */
    public function profile(string $token, array $connectedAccountIds = []): array
    {
        if (self::usesUnifiedLogin()) {
            $account = $this->pickSharedAccount($token, $this->read($token)->get('/me/assigned_instagram_accounts', ['fields' => 'id,username'])->throw()->json('data') ?? [], $connectedAccountIds);

            if ($account === null) {
                return [];
            }

            // Best effort: the account still connects when Meta does not return its picture or follower count.
            $details = rescue(fn () => $this->read($token)->get("/{$account['id']}", ['fields' => 'profile_picture_url,followers_count'])->throw()->json(), [], report: false);

            return array_filter([
                'user_id' => (string) $account['id'],
                'username' => $account['username'],
                'meta_user_id' => self::metaUserId($this->read($token)),
                'profile_picture_url' => $details['profile_picture_url'] ?? null,
                'followers_count' => isset($details['followers_count']) ? (int) $details['followers_count'] : null,
            ], fn ($value): bool => $value !== null);
        }

        return $this->read($token)->get('/me', [
            'fields' => 'user_id,username,account_type,profile_picture_url,followers_count',
        ])->throw()->json();
    }

    /**
     * @return array{caption?: string, media_product_type?: string, media_type?: string, timestamp?: string, thumbnail_url?: string, media_url?: string, permalink?: string, like_count?: int, comments_count?: int}
     */
    public function media(Channel $channel, string $mediaId): array
    {
        return $this->read($channel->access_token)->get("/{$mediaId}", [
            'fields' => 'caption,media_type,media_product_type,timestamp,thumbnail_url,media_url,permalink,like_count,comments_count',
        ])->throw()->json();
    }

    /**
     * The account's latest posts and reels, newest first.
     *
     * @return list<array{id: string, caption?: string, media_product_type?: string, media_type?: string, timestamp?: string, thumbnail_url?: string, media_url?: string, permalink?: string, like_count?: int, comments_count?: int}>
     */
    public function recentMedia(Channel $channel, int $limit = 50): array
    {
        return $this->read($channel->access_token)->get(self::usesUnifiedLogin() ? "/{$channel->account_id}/media" : '/me/media', [
            'fields' => 'id,caption,media_type,media_product_type,timestamp,thumbnail_url,media_url,permalink,like_count,comments_count',
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

    /**
     * Asks Meta to send the account's comments and messages. False, with nothing subscribed, when Facebook
     * Login shared Pages but none is linked to this account: Meta delivers its webhooks through that Page,
     * so they would never arrive. With no Page listed at all (a Page reached through a business portfolio
     * is not), the account still connects and the reason is logged.
     */
    public function subscribeToWebhooks(string $token, string $accountId): bool
    {
        if (self::usesUnifiedLogin() && $this->sharedPages($token) !== [] && $this->linkedPage($token, $accountId) === null) {
            Log::warning('Instagram account is linked to none of the Facebook Pages shared with the app; refused, as its webhooks would not arrive.', ['account_id' => $accountId]);

            return false;
        }

        $this->graph($token)->post((self::usesUnifiedLogin() ? "/{$accountId}" : '/me').'/subscribed_apps?subscribed_fields=comments,messages')->throw();

        if (self::usesUnifiedLogin()) {
            $this->subscribeLinkedPage($token, $accountId);
        }

        return true;
    }

    /**
     * The shared Facebook Page the Instagram account is linked to, with its token.
     *
     * @return array{id: string, access_token: string}|null
     */
    private function linkedPage(string $token, string $accountId): ?array
    {
        $page = collect($this->sharedPages($token))->first(fn (array $page): bool => (string) data_get($page, 'instagram_business_account.id') === $accountId);

        return $page === null || blank($page['access_token'] ?? null) ? null : $page;
    }

    /**
     * With Facebook Login, Meta delivers an Instagram account's comment and message webhooks only once
     * the app is installed on the Facebook Page the account is linked to. Best effort: an account with
     * no linked Page, or a Page that refuses, still connects, and the reason is logged.
     */
    private function subscribeLinkedPage(string $token, string $accountId): void
    {
        $page = $this->linkedPage($token, $accountId);

        if ($page === null) {
            Log::warning('Instagram account has no linked Facebook Page the app can subscribe to; its webhooks will not arrive.', ['account_id' => $accountId]);

            return;
        }

        rescue(fn () => $this->graph($page['access_token'])->post("/{$page['id']}/subscribed_apps", ['subscribed_fields' => 'feed'])->throw());
    }

    /**
     * The Facebook Pages the person shared with the app, with each Page's token and linked Instagram
     * account. Best effort: empty when Meta refuses or the login shared no Page.
     *
     * @return list<array{id: string, access_token?: string, instagram_business_account?: array{id: string}}>
     */
    private function sharedPages(string $token): array
    {
        $pages = rescue(fn () => $this->read($token)->get('/me/accounts', [
            'fields' => 'id,access_token,instagram_business_account',
            'limit' => 100,
        ])->throw()->json('data'), [], report: false) ?? [];

        return array_values(array_filter((array) $pages, fn ($page): bool => is_array($page)));
    }

    /**
     * The account the person picked in Meta's dialog. Meta lists every account the business has shared
     * with the app, so the token's own grants (`granular_scopes`) narrow the list when they can. When
     * several remain, one linked to a Facebook Page shared in this login is preferred (the Page the
     * person picked with it), then one the person has not connected yet (adding a second account);
     * otherwise the first, as before.
     *
     * @param  list<array{id: string, username: string}>  $accounts
     * @param  list<string>  $connectedAccountIds
     * @return array{id: string, username: string}|null
     */
    private function pickSharedAccount(string $token, array $accounts, array $connectedAccountIds = []): ?array
    {
        if (count($accounts) < 2) {
            return $accounts[0] ?? null;
        }

        $granted = $this->grantedInstagramIds($token);
        $candidates = array_values(array_filter($accounts, fn (array $account): bool => in_array((string) $account['id'], $granted, true))) ?: $accounts;

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $pageLinkedIds = collect($this->sharedPages($token))
            ->map(fn (array $page): string => (string) data_get($page, 'instagram_business_account.id'))
            ->filter()
            ->all();
        $candidates = array_values(array_filter($candidates, fn (array $account): bool => in_array((string) $account['id'], $pageLinkedIds, true))) ?: $candidates;

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $notConnected = array_values(array_filter($candidates, fn (array $account): bool => ! in_array((string) $account['id'], $connectedAccountIds, true)));

        return $notConnected[0] ?? $candidates[0];
    }

    /**
     * The Instagram account ids this token's grants name, from Meta's token debugger. Best effort.
     *
     * @return list<string>
     */
    private function grantedInstagramIds(string $token): array
    {
        $scopes = rescue(fn () => $this->read()->get('/debug_token', [
            'input_token' => $token,
            'access_token' => config('services.instagram.app_id').'|'.config('services.instagram.app_secret'),
        ])->throw()->json('data.granular_scopes'), [], report: false) ?? [];

        return collect($scopes)
            ->filter(fn ($scope): bool => is_array($scope) && str_starts_with((string) ($scope['scope'] ?? ''), 'instagram'))
            ->flatMap(fn (array $scope): array => (array) ($scope['target_ids'] ?? []))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
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

    /**
     * The person's app-scoped Facebook id, kept so Meta's deletion callbacks can find their channels.
     * Best effort: a channel still connects when Meta does not answer.
     */
    public static function metaUserId(PendingRequest $request): ?string
    {
        $id = rescue(fn () => $request->get('/me', ['fields' => 'id'])->throw()->json('id'), report: false);

        return is_scalar($id) && $id !== '' ? (string) $id : null;
    }

    public static function usesUnifiedLogin(): bool
    {
        return filled(config('services.instagram.config_id'));
    }

    private function read(?string $token = null): PendingRequest
    {
        return $this->graph($token)->retry([200, 1000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)));
    }

    private function graph(?string $token): PendingRequest
    {
        $host = self::usesUnifiedLogin() ? 'https://graph.facebook.com/' : 'https://graph.instagram.com/';
        $request = Http::baseUrl($host.config('services.instagram.graph_version'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);

        return $token === null ? $request : $request->withToken($token);
    }
}
