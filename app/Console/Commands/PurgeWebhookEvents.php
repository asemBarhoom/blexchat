<?php

namespace App\Console\Commands;

use App\Models\MessageDeliveryAttempt;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class PurgeWebhookEvents extends Command
{
    protected $signature = 'webhooks:purge {--days=30}';

    protected $description = 'Remove old webhook delivery records';

    public function handle(): int
    {
        WebhookEvent::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
        MessageDeliveryAttempt::where('created_at', '<', now()->subDays((int) $this->option('days')))->where('status', 'sent')->delete();

        return self::SUCCESS;
    }
}
