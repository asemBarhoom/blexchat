<?php

use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use App\Models\ChannelConnectScreen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('opens the connect screen in the state the owner is in', function () {
    $screen = new ChannelConnectScreen;

    expect($screen->stateFor(null, null))->toBe('default')
        ->and($screen->stateFor(new Channel(['status' => 'active']), new ChannelAccessRequest))->toBe('connected')
        ->and($screen->stateFor(new Channel(['status' => 'expired']), new ChannelAccessRequest))->toBe('reconnect')
        ->and($screen->stateFor(null, new ChannelAccessRequest))->toBe('requested');
});

it('stamps the decision when a request is approved or declined, and clears it when pending again', function () {
    $request = ChannelAccessRequest::factory()->create();
    expect($request->decided_at)->toBeNull()->and($request->isPending())->toBeTrue();

    $request->update(['status' => 'approved']);
    expect($request->decided_at)->not->toBeNull();

    $request->update(['note' => 'checked']);
    expect($request->decided_at)->not->toBeNull();

    $request->update(['status' => 'pending']);
    expect($request->decided_at)->toBeNull();
});

it('reads an access token only for the owner it was issued to, while fresh, for another workspace’s channel', function () {
    $owner = User::factory()->create();
    $asker = User::factory()->create();
    $channel = Channel::factory()->for($owner)->create();

    $token = ChannelAccessRequest::tokenFor($asker, $channel);

    expect(ChannelAccessRequest::channelFromToken($token, $asker)?->is($channel))->toBeTrue()
        ->and(ChannelAccessRequest::channelFromToken($token, $owner))->toBeNull()
        ->and(ChannelAccessRequest::channelFromToken('tampered', $asker))->toBeNull()
        ->and(ChannelAccessRequest::channelFromToken(ChannelAccessRequest::tokenFor($owner, $channel), $owner))->toBeNull();

    $this->travel(ChannelAccessRequest::TOKEN_MINUTES + 1)->minutes();
    expect(ChannelAccessRequest::channelFromToken($token, $asker))->toBeNull();
});
