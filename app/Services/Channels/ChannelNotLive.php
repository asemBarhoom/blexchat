<?php

namespace App\Services\Channels;

use RuntimeException;

/**
 * The platform cannot send replies through its API yet (TikTok).
 */
class ChannelNotLive extends RuntimeException
{
    public function __construct(public string $platform)
    {
        parent::__construct("Replies on {$platform} are not available yet.");
    }
}
