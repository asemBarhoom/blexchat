<?php

namespace App\Services\Instagram;

use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Instagram for development (INSTAGRAM_DRIVER=fake): no Meta app, no network. Signing in opens a
 * signed test page instead of Instagram's; the connected account has demo posts; replies and DMs
 * are logged instead of sent. Incoming comments and DMs are simulated with
 * POST /api/v1/channels/{channel}/test-events, which runs the real webhook handling.
 */
class FakeInstagramClient extends InstagramClient
{
    /** The code the test page sends back: the account kind and the owner, e.g. business-12. */
    public const KINDS = ['business', 'personal'];

    private const TOKEN_PREFIX = 'fake_ig_';

    /** About 60 days, like Instagram's long-lived tokens. */
    private const TOKEN_SECONDS = 5_184_000;

    public static function active(): bool
    {
        return config('services.instagram.driver') === 'fake' && self::allowed();
    }

    public static function allowed(): bool
    {
        return ! app()->isProduction() || (bool) config('services.instagram.allow_fake');
    }

    /**
     * Where the test page sends the owner back: the frontend page that posts `code` and `state`.
     */
    public static function returnUrl(): string
    {
        return config('services.instagram.redirect_uri') ?: rtrim(config('app.frontend_url'), '/').'/onboarding/connect';
    }

    public static function code(string $kind, int $userId): string
    {
        return "{$kind}-{$userId}";
    }

    public function authorizationUrl(string $state): string
    {
        return URL::temporarySignedRoute('channels.fake-instagram.show', now()->addMinutes(15), ['state' => $state]);
    }

    public function exchangeCode(string $code): array
    {
        return ['access_token' => self::TOKEN_PREFIX.$code, 'expires_in' => self::TOKEN_SECONDS];
    }

    public function refreshToken(string $token): array
    {
        return ['access_token' => $token, 'expires_in' => self::TOKEN_SECONDS];
    }

    /**
     * One test account per owner, so two owners never collide on "already connected".
     */
    public function profile(string $token): array
    {
        [$kind, $userId] = array_pad(explode('-', Str::after($token, self::TOKEN_PREFIX), 2), 2, '0');

        return [
            'user_id' => 'fake-'.$userId,
            'username' => 'test_shop_'.$userId,
            'account_type' => $kind === 'personal' ? 'PERSONAL' : 'BUSINESS',
            'followers_count' => 1280,
        ];
    }

    public function media(Channel $channel, string $mediaId): array
    {
        return collect($this->recentMedia($channel))->firstWhere('id', $mediaId)
            ?? ['caption' => 'Test post', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'timestamp' => now()->toIso8601String(), 'like_count' => 0, 'comments_count' => 0];
    }

    public function recentMedia(Channel $channel, int $limit = 50): array
    {
        $posts = [
            ['caption' => 'New abayas just landed. Comment "price" for the list.', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'like_count' => 214, 'comments_count' => 38],
            ['caption' => 'How we style the black kaftan, in 30 seconds.', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'like_count' => 1320, 'comments_count' => 95],
            ['caption' => 'Eid collection: 20% off this week only.', 'media_type' => 'CAROUSEL_ALBUM', 'media_product_type' => 'FEED', 'like_count' => 487, 'comments_count' => 61],
            ['caption' => 'Behind the scenes at the workshop.', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'like_count' => 96, 'comments_count' => 7],
        ];

        return collect($posts)->take($limit)->map(fn (array $post, int $index): array => [
            'id' => "fake-{$channel->id}-post-".($index + 1),
            'timestamp' => now()->subDays($index * 3 + 1)->toIso8601String(),
            ...$post,
        ])->all();
    }

    public function contact(Channel $channel, string $instagramScopedId): array
    {
        return ['username' => Str::after($instagramScopedId, 'fake-customer-')];
    }

    public function subscribeToWebhooks(string $token, string $accountId): void {}

    public function replyToComment(Channel $channel, string $commentId, string $text): ?string
    {
        return $this->sent('comment reply', ['comment' => $commentId, 'text' => $text]);
    }

    public function sendPrivateReply(Channel $channel, string $commentId, array $message): ?string
    {
        return $this->sent('private reply', ['comment' => $commentId, 'message' => $message]);
    }

    public function sendMessage(Channel $channel, string $recipientId, array $message): ?string
    {
        return $this->sent('DM', ['recipient' => $recipientId, 'message' => $message]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function sent(string $what, array $details): string
    {
        Log::info("Fake Instagram {$what} (not sent)", $details);

        return 'fake_'.Str::random(16);
    }
}
