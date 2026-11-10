<?php

namespace App\Services\Channels;

use App\Models\Message;

/**
 * What one retry did: sent it now, found it already sent, or failed with a code the caller shapes.
 */
final class DeliveryRetryResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly bool $alreadySent = false,
        public readonly ?Message $message = null,
        public readonly ?string $platformMessageId = null,
        public readonly ?string $errorCode = null,
    ) {}
}
