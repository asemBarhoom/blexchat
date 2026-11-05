<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Instagram\AutomationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The automation's follow-up, once its wait is over, for someone who neither tapped the DM's
 * button nor wrote back. Tried once: Instagram has no idempotency key, so a retry could send twice.
 */
class SendAutomationFollowUp implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Message $dm) {}

    public function handle(AutomationRunner $automations): void
    {
        $automations->followUp($this->dm);
    }
}
