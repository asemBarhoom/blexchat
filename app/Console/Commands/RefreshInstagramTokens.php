<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Instagram\InstagramClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

#[Signature('instagram:refresh-tokens')]
#[Description('Renew Instagram tokens that expire within a week; mark channels whose token cannot be renewed as expired')]
class RefreshInstagramTokens extends Command
{
    public function handle(InstagramClient $instagram): int
    {
        $channels = Channel::query()
            ->where('platform', 'instagram')
            ->where('status', 'active')
            ->where('token_expires_at', '<=', now()->addWeek())
            ->get();

        foreach ($channels as $channel) {
            try {
                $token = $instagram->refreshToken($channel->access_token);
                $channel->update(['access_token' => $token['access_token'], 'token_expires_at' => now()->addSeconds($token['expires_in'])]);
                $this->info("Renewed @{$channel->username}");
            } catch (RequestException $exception) {
                $channel->update(['status' => 'expired']);
                $this->warn("@{$channel->username} must reconnect: ".$exception->response->json('error.message'));
            }
        }

        return self::SUCCESS;
    }
}
