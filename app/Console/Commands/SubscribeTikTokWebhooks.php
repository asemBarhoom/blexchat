<?php

namespace App\Console\Commands;

use App\Services\Channels\ChannelOAuthProvider;
use App\Services\TikTok\TikTokClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Points the TikTok for Business app's direct-message and comment webhooks at this API. App-wide:
 * run once after TikTok approves the app, and again if the API's address changes.
 */
class SubscribeTikTokWebhooks extends Command
{
    protected $signature = 'tiktok:subscribe-webhooks';

    protected $description = 'Point the TikTok for Business app’s direct-message and comment webhooks at this API';

    public function handle(TikTokClient $tiktok): int
    {
        if (! ChannelOAuthProvider::credentialsConfigured('tiktok')) {
            $this->error('TikTok is not set up: TIKTOK_CLIENT_ID, TIKTOK_CLIENT_SECRET and TIKTOK_AUTH_URL are needed.');

            return self::FAILURE;
        }

        $url = route('webhooks.tiktok');

        foreach (TikTokClient::WEBHOOK_EVENT_TYPES as $eventType) {
            try {
                $tiktok->subscribeWebhook($eventType, $url);
                $this->info("{$eventType} → {$url}");
            } catch (RequestException|ConnectionException $exception) {
                $this->error("{$eventType}: ".$exception->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
