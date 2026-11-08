<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Services\Channels\ChannelEventHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * WhatsApp Cloud API and Messenger webhook events, handled in the background. WhatsApp names the
 * business number by its phone number id, Messenger the Page by its id: the channel's account_id.
 */
class ProcessMetaChannelWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param  'whatsapp'|'messenger'  $platform
     * @param  list<array<string, mixed>>  $entries
     */
    public function __construct(public string $platform, public array $entries) {}

    public function handle(ChannelEventHandler $handler): void
    {
        foreach ($this->entries as $entry) {
            $this->platform === 'whatsapp' ? $this->whatsapp($handler, $entry) : $this->messenger($handler, $entry);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function whatsapp(ChannelEventHandler $handler, array $entry): void
    {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];
            $channel = $this->channel((string) data_get($value, 'metadata.phone_number_id'));

            if ($channel === null) {
                continue;
            }

            $names = collect($value['contacts'] ?? [])->mapWithKeys(fn (array $contact): array => [(string) ($contact['wa_id'] ?? '') => data_get($contact, 'profile.name')]);

            foreach ($value['messages'] ?? [] as $message) {
                $from = (string) ($message['from'] ?? '');
                $handler->message(
                    $channel,
                    $from,
                    (string) ($message['id'] ?? ''),
                    data_get($message, 'text.body') ?? '['.($message['type'] ?? 'attachment').']',
                    $names[$from] ?? null,
                    $from !== '' ? '+'.$from : null,
                    isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : null,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function messenger(ChannelEventHandler $handler, array $entry): void
    {
        $channel = $this->channel((string) ($entry['id'] ?? ''));

        foreach ($channel === null ? [] : $entry['messaging'] ?? [] as $event) {
            $message = $event['message'] ?? null;

            // Echoes are the Page's own replies, stored when they were sent.
            if (! is_array($message) || ! empty($message['is_echo'])) {
                continue;
            }

            $handler->message(
                $channel,
                (string) data_get($event, 'sender.id'),
                (string) ($message['mid'] ?? ''),
                $message['text'] ?? '['.data_get($message, 'attachments.0.type', 'attachment').']',
                sentAt: isset($event['timestamp']) ? Carbon::createFromTimestampMs($event['timestamp']) : null,
            );
        }
    }

    private function channel(string $accountId): ?Channel
    {
        return $accountId === '' ? null : Channel::query()
            ->where('platform', $this->platform)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->first();
    }
}
