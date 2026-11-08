<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChannelOAuthController extends Controller
{
    public function start(Request $request, string $platform): JsonResponse
    {
        abort_unless(in_array($platform, ['messenger', 'whatsapp', 'tiktok'], true), 404);
        $state = Str::random(40);
        Cache::put("channel-oauth:{$state}", ['user_id' => $request->user()->id, 'platform' => $platform], now()->addMinutes(10));

        return response()->json(['data' => ['platform' => $platform, 'state' => $state, 'authorization_url' => url("/api/v1/channels/oauth/{$platform}/callback?state={$state}")]]);
    }

    public function callback(Request $request, string $platform): JsonResponse
    {
        $state = (string) $request->query('state');
        $pending = Cache::pull("channel-oauth:{$state}");
        abort_unless(is_array($pending) && $pending['platform'] === $platform, 400, 'Invalid OAuth state.');

        $user = User::findOrFail($pending['user_id']);
        $accountId = (string) ($request->input('page_id') ?: $request->input('phone_number_id') ?: $request->input('open_id'));
        abort_if($accountId === '', 422, 'The provider did not return an account id.');

        $channel = Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform, 'account_id' => $accountId],
            ['access_token' => (string) $request->input('access_token', 'fake-oauth-token'), 'status' => 'active', 'connected_at' => now()],
        );

        return response()->json(['data' => ['channel' => $channel->id, 'platform' => $platform, 'account_id' => $accountId]], 201);
    }
}
