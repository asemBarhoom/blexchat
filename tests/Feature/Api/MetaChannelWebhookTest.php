<?php

use App\Jobs\ProcessMetaChannelWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('verifies Meta webhook tokens and rejects invalid tokens', function () {
    config()->set('services.meta.webhook_verify_token', 'verify-me');

    $this->get('/api/v1/webhooks/messenger?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=challenge')
        ->assertOk()
        ->assertSeeText('challenge');

    $this->get('/api/v1/webhooks/messenger?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=challenge-dot')
        ->assertOk()
        ->assertSeeText('challenge-dot');

    $this->get('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=wa-challenge')
        ->assertOk()
        ->assertSeeText('wa-challenge');

    $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=wa-challenge-dot')
        ->assertOk()
        ->assertSeeText('wa-challenge-dot');

    $this->get('/api/v1/webhooks/messenger?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=challenge')
        ->assertForbidden();

    $this->get('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=challenge')
        ->assertForbidden();
});

it('authenticates and queues Meta webhook payloads exactly once per request', function () {
    Queue::fake();
    config()->set('services.meta.app_secret', 'app-secret');
    $payload = ['object' => 'page', 'entry' => [['id' => 'page-1', 'messaging' => []]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $this->call('POST', '/api/v1/webhooks/messenger', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk();

    Queue::assertPushed(ProcessMetaChannelWebhook::class, fn (ProcessMetaChannelWebhook $job): bool => $job->platform === 'messenger');

    $this->call('POST', '/api/v1/webhooks/messenger', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong',
    ], $body)->assertForbidden();

    $waPayload = ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'wa-1', 'changes' => []]]];
    $waBody = json_encode($waPayload, JSON_THROW_ON_ERROR);
    $waSignature = 'sha256='.hash_hmac('sha256', $waBody, 'app-secret');

    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $waSignature,
    ], $waBody)->assertOk();

    Queue::assertPushed(ProcessMetaChannelWebhook::class, fn (ProcessMetaChannelWebhook $job): bool => $job->platform === 'whatsapp');

    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong',
    ], $waBody)->assertForbidden();
});
