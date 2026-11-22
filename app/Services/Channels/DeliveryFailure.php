<?php

namespace App\Services\Channels;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Names why a reply could not be sent, so the inbox can say it plainly instead of a raw provider error.
 */
class DeliveryFailure
{
    /**
     * Meta refuses a message outside its messaging window: 24 hours after the person last wrote
     * (Messenger 10/2018278, Instagram 10/2534022, WhatsApp 131047), or a second private reply to a comment.
     */
    private const OUTSIDE_WINDOW_SUBCODES = [2018278, 2534022];

    private const OUTSIDE_WINDOW_CODES = [131047];

    public static function code(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ChannelNotLive => 'channel_not_live',
            self::isOutsideWindow($exception) => 'outside_window',
            default => 'provider_error',
        };
    }

    /**
     * Meta no longer accepts the channel's token (OAuth error 190, or a 401), so it must be connected again.
     */
    public static function isTokenRejected(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return false;
        }

        return $exception->response->status() === 401
            || (int) $exception->response->json('error.code', 0) === 190;
    }

    public static function isOutsideWindow(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return false;
        }

        $error = (array) $exception->response->json('error', []);

        return in_array((int) ($error['error_subcode'] ?? 0), self::OUTSIDE_WINDOW_SUBCODES, true)
            || in_array((int) ($error['code'] ?? 0), self::OUTSIDE_WINDOW_CODES, true);
    }
}
