<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Services\Instagram\InstagramEventHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stores what an Instagram webhook reported and runs matching automations. Safe to retry:
 * events already stored are skipped, so a retry never replies twice.
 */
class ProcessInstagramWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function __construct(public array $entries) {}

    public function handle(InstagramEventHandler $handler): void
    {
        foreach ($this->entries as $entry) {
            $channel = Channel::query()
                ->where('platform', 'instagram')
                ->where('account_id', (string) ($entry['id'] ?? ''))
                ->where('status', 'active')
                ->first();

            if ($channel === null) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) === 'comments') {
                    $handler->comment($channel, $change['value'] ?? []);
                }
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                $handler->message($channel, $event);
            }
        }
    }
}
