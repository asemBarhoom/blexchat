<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class PurgeWebhookEvents extends Command
{
    protected $signature = 'webhooks:purge {--days=30}';

    protected $description = 'Remove old webhook delivery records';

    public function handle(): int
    {
        WebhookEvent::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();

        return self::SUCCESS;
    }
}
