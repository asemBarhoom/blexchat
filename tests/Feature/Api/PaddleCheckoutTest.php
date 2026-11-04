<?php

use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaddleGateway;
use Database\Seeders\CheckoutSeeder;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, CheckoutSeeder::class]);
    config([
        'services.payments.driver' => 'paddle',
        'services.payments.currency' => 'usd',
        'services.paddle.environment' => 'sandbox',
        'services.paddle.api_key' => 'pdl_sdbx_apikey_fake',
        'services.paddle.webhook_secret' => 'pdl_ntfset_fake',
        'services.paddle.client_token' => 'test_fake',
        'app.frontend_url' => 'https://app.example.test',
    ]);
    Http::preventStrayRequests();
    $this->user = User::factory()->create(['email' => 'owner@example.test']);
});

const PADDLE_TXN = 'txn_01hv8wptq8987qeep44cyrewp9';

function paddleTransactionCreated(): array
{
    return ['sandbox-api.paddle.com/transactions' => Http::response(['data' => [
        'id' => PADDLE_TXN,
        'status' => 'draft',
        'checkout' => ['url' => 'https://blexchat.test/payments/paddle?_ptxn='.PADDLE_TXN],
    ]], 201)];
}

/**
 * Posts an event to the webhook, signed with the test secret the way Paddle signs it.
 */
function sendPaddleEvent(string $type, array $data, ?int $timestamp = null, ?string $signature = null)
{
    $payload = json_encode(['event_id' => 'evt_'.uniqid(), 'event_type' => $type, 'occurred_at' => now()->toIso8601String(), 'data' => $data]);

    return test()->call('POST', '/api/v1/webhooks/paddle', server: [
        'HTTP_PADDLE_SIGNATURE' => $signature ?? PaddleGateway::signature($payload, $timestamp),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload);
}

function openPaddleSession(User $user, string $plan = 'pro', array $attributes = []): CheckoutSession
{
    return CheckoutSession::factory()->for($user)->create([
        'payment_provider' => 'paddle',
        'plan_id' => Plan::where('slug', $plan)->value('id'),
        'payment_session_id' => PADDLE_TXN,
        ...$attributes,
    ]);
}

describe('POST /api/v1/checkout/session', function () {
    it('creates a Paddle transaction priced from the plans table, with the coupon as a one-off discount', function () {
        Http::fake(paddleTransactionCreated());

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro', 'billing' => 'annual', 'coupon' => 'blex20'])
            ->assertCreated()
            ->assertJsonPath('data.id', PADDLE_TXN)
            ->assertJsonPath('data.url', 'https://blexchat.test/payments/paddle?_ptxn='.PADDLE_TXN)
            ->assertJsonPath('data.coupon', 'BLEX20')
            ->assertJsonPath('data.amount_total', 278.4);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer pdl_sdbx_apikey_fake')
            && $request['currency_code'] === 'USD'
            && $request['items'][0]['price']['unit_price'] === ['amount' => '34800', 'currency_code' => 'USD']
            && $request['items'][0]['price']['billing_cycle'] === ['interval' => 'year', 'frequency' => 1]
            && $request['items'][0]['price']['product']['tax_category'] === 'standard'
            && $request['discount'] === ['type' => 'flat', 'amount' => '6960', 'description' => 'BLEX20 — 20% off', 'recur' => false]
            && $request['custom_data'] === ['user_id' => (string) $this->user->id, 'plan' => 'pro', 'billing' => 'annual']
            && ! isset($request['customer_id']));

        expect(CheckoutSession::sole())->payment_provider->toBe('paddle')->amount_total->toBe(27840);
    });

    it('prefills a returning Paddle customer and sends no discount without a coupon', function () {
        $this->user->update(['payment_customer_id' => 'ctm_01returning']);
        Http::fake(paddleTransactionCreated());

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'business'])->assertCreated()->assertJsonPath('data.amount_total', 99);

        Http::assertSent(fn (Request $request) => $request['customer_id'] === 'ctm_01returning'
            && $request['items'][0]['price']['billing_cycle']['interval'] === 'month'
            && ! isset($request['discount']));
    });

    it('reports a Paddle account with no default payment link as a provider error', function () {
        Http::fake(['sandbox-api.paddle.com/transactions' => Http::response(['data' => ['id' => PADDLE_TXN, 'checkout' => ['url' => null]]], 201)]);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'payment_provider_error');

        expect(CheckoutSession::count())->toBe(0);
    });

    it('says so when Paddle refuses the key', function () {
        Http::fake(['sandbox-api.paddle.com/transactions' => Http::response(['error' => ['code' => 'invalid_token']], 403)]);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])->assertStatus(502);
    });

    it('says card payments are unavailable without an API key', function () {
        config(['services.paddle.api_key' => null]);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'payments_unavailable');
    });

    it('uses the live API in production', function () {
        config(['services.paddle.environment' => 'production']);
        Http::fake(['api.paddle.com/transactions' => Http::response(['data' => ['id' => PADDLE_TXN, 'checkout' => ['url' => 'https://blexchat.test/payments/paddle?_ptxn='.PADDLE_TXN]]], 201)]);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])->assertCreated();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.paddle.com/transactions');
    });
});

it('answers a paid plan on the plan screen with the Paddle checkout', function () {
    Http::fake(paddleTransactionCreated());

    $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'pro'])
        ->assertAccepted()
        ->assertJsonPath('data.url', 'https://blexchat.test/payments/paddle?_ptxn='.PADDLE_TXN);
});

describe('POST /api/v1/webhooks/paddle', function () {
    it('refuses an event without a valid signature', function (Closure $signature) {
        openPaddleSession($this->user);

        sendPaddleEvent('subscription.created', ['id' => 'sub_1', 'transaction_id' => PADDLE_TXN], signature: $signature())->assertBadRequest();

        expect($this->user->subscription()->exists())->toBeFalse();
    })->with([
        'no header' => [fn () => ''],
        'wrong h1' => [fn () => 'ts='.time().';h1=deadbeef'],
        'no timestamp' => [fn () => 'h1=deadbeef'],
    ]);

    it('refuses a correctly signed event older than the tolerance', function () {
        openPaddleSession($this->user);

        sendPaddleEvent('subscription.created', ['id' => 'sub_1', 'transaction_id' => PADDLE_TXN], timestamp: time() - 6)->assertBadRequest();
    });

    it('puts the paid plan in place once, however often Paddle reports it', function () {
        Subscription::factory()->for($this->user)->trialEnded()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);
        openPaddleSession($this->user, 'pro', ['billing_period' => 'annual', 'coupon_id' => Coupon::where('code', 'BLEX20')->value('id')]);
        $completed = [
            'id' => PADDLE_TXN,
            'status' => 'completed',
            'customer_id' => 'ctm_01new',
            'subscription_id' => 'sub_01new',
            'billing_period' => ['starts_at' => '2026-11-04T10:00:00Z', 'ends_at' => '2027-11-04T10:00:00Z'],
        ];

        sendPaddleEvent('transaction.completed', $completed)->assertOk();
        sendPaddleEvent('transaction.completed', $completed)->assertOk();
        sendPaddleEvent('subscription.created', ['id' => 'sub_01new', 'transaction_id' => PADDLE_TXN, 'customer_id' => 'ctm_01new', 'status' => 'active'])->assertOk();

        expect($this->user->subscription->fresh())
            ->plan->slug->toBe('pro')
            ->billing_period->toBe('annual')
            ->status->toBe('active')
            ->trial_used_at->not->toBeNull()
            ->payment_provider->toBe('paddle')
            ->payment_subscription_id->toBe('sub_01new')
            ->current_period_end->toIso8601ZuluString()->toBe('2027-11-04T10:00:00Z')
            ->and($this->user->fresh()->payment_customer_id)->toBe('ctm_01new')
            ->and(CheckoutSession::sole()->status)->toBe('complete')
            ->and(Coupon::where('code', 'BLEX20')->value('times_used'))->toBe(1);
        Http::assertNothingSent();
    });

    it('puts the plan in place from subscription.created when it arrives first', function () {
        openPaddleSession($this->user);

        sendPaddleEvent('subscription.created', [
            'id' => 'sub_01new',
            'transaction_id' => PADDLE_TXN,
            'customer_id' => 'ctm_01new',
            'current_billing_period' => ['starts_at' => '2026-11-04T10:00:00Z', 'ends_at' => '2026-12-04T10:00:00Z'],
        ])->assertOk();

        expect($this->user->subscription->fresh())->plan->slug->toBe('pro')->payment_subscription_id->toBe('sub_01new');
    });

    it('cancels at Paddle the card plan a new one replaces', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'payment_provider' => 'paddle', 'payment_subscription_id' => 'sub_old']);
        openPaddleSession($this->user, 'business');
        Http::fake(['sandbox-api.paddle.com/subscriptions/sub_old/cancel' => Http::response(['data' => ['id' => 'sub_old', 'status' => 'canceled']])]);

        sendPaddleEvent('subscription.created', ['id' => 'sub_new', 'transaction_id' => PADDLE_TXN])->assertOk();

        expect($this->user->subscription->fresh())->plan->slug->toBe('business')->payment_subscription_id->toBe('sub_new');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://sandbox-api.paddle.com/subscriptions/sub_old/cancel' && $request['effective_from'] === 'immediately');
    });

    it('moves the paid period on with a renewal, never back', function () {
        $subscription = Subscription::factory()->for($this->user)->create([
            'plan_id' => Plan::where('slug', 'pro')->value('id'),
            'payment_provider' => 'paddle',
            'payment_subscription_id' => 'sub_1',
            'current_period_end' => '2026-12-04 10:00:00',
        ]);
        $renewal = fn (string $end) => ['id' => 'txn_01renewal'.substr(md5($end), 0, 8), 'status' => 'completed', 'subscription_id' => 'sub_1', 'billing_period' => ['ends_at' => $end]];

        sendPaddleEvent('transaction.completed', $renewal('2027-01-04T10:00:00Z'))->assertOk();
        sendPaddleEvent('subscription.updated', ['id' => 'sub_1', 'status' => 'active', 'current_billing_period' => ['ends_at' => '2026-12-04T10:00:00Z']])->assertOk();

        expect($subscription->fresh()->current_period_end->toIso8601ZuluString())->toBe('2027-01-04T10:00:00Z');
    });

    it('drops the account to Free when Paddle cancels, and ignores a subscription already replaced', function (string $type, array $data) {
        $subscription = Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'payment_provider' => 'paddle', 'payment_subscription_id' => 'sub_1']);

        sendPaddleEvent($type, ['id' => 'sub_other', 'status' => 'canceled'])->assertOk();
        expect($subscription->fresh()->plan->slug)->toBe('pro');

        sendPaddleEvent($type, $data)->assertOk();
        sendPaddleEvent($type, $data)->assertOk();
        expect($subscription->fresh())->plan->slug->toBe('free')->payment_subscription_id->toBeNull()->payment_provider->toBeNull();
    })->with([
        'subscription.canceled' => ['subscription.canceled', ['id' => 'sub_1', 'status' => 'canceled', 'current_billing_period' => null]],
        'subscription.updated to canceled' => ['subscription.updated', ['id' => 'sub_1', 'status' => 'canceled']],
    ]);
});

describe('cancelling', function () {
    it('cancels at Paddle on DELETE /subscription', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'payment_provider' => 'paddle', 'payment_subscription_id' => 'sub_1']);
        Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1/cancel' => Http::response(['data' => ['id' => 'sub_1', 'status' => 'canceled']])]);

        $this->actingAs($this->user)->deleteJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'free')
            ->assertJsonPath('data.paid_by_card', false);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://sandbox-api.paddle.com/subscriptions/sub_1/cancel'
            && $request['effective_from'] === 'immediately');
    });

    it('keeps the plan when Paddle cannot cancel it', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'payment_provider' => 'paddle', 'payment_subscription_id' => 'sub_1']);
        Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1/cancel' => Http::response([], 500)]);

        $this->actingAs($this->user)->deleteJson('/api/v1/subscription')
            ->assertStatus(502)
            ->assertJsonPath('code', 'payment_provider_error');

        expect($this->user->subscription->fresh()->plan->slug)->toBe('pro');
    });

    it('does not call Paddle for a plan another provider was paying', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'payment_provider' => 'stripe', 'payment_subscription_id' => 'sub_legacy']);
        Http::fake();

        $this->actingAs($this->user)->deleteJson('/api/v1/subscription')->assertOk()->assertJsonPath('data.plan.id', 'free');

        Http::assertNothingSent();
    });
});

describe('GET /api/v1/checkout/session/{id}', function () {
    it('asks Paddle while the webhook has not arrived, and shows the plan once paid', function () {
        openPaddleSession($this->user);
        Http::fake(['sandbox-api.paddle.com/transactions/'.PADDLE_TXN => Http::response(['data' => [
            'id' => PADDLE_TXN,
            'status' => 'completed',
            'subscription_id' => 'sub_01new',
            'customer_id' => 'ctm_01new',
            'billing_period' => ['ends_at' => '2026-12-04T10:00:00Z'],
        ]])]);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/checkout/session/'.PADDLE_TXN)
            ->assertOk()
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonPath('data.subscription.plan.id', 'pro')
            ->assertJsonPath('data.subscription.paid_by_card', true);
    });

    it('answers "not yet" while the transaction is still open at Paddle', function () {
        openPaddleSession($this->user);
        Http::fake(['sandbox-api.paddle.com/transactions/'.PADDLE_TXN => Http::response(['data' => ['id' => PADDLE_TXN, 'status' => 'ready', 'subscription_id' => null]])]);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/checkout/session/'.PADDLE_TXN)
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonMissingPath('data.subscription');
    });
});

describe('GET /payments/paddle (the default payment link)', function () {
    it('loads Paddle.js in sandbox with the client-side token and the success page for the transaction', function () {
        $this->get('/payments/paddle?_ptxn='.PADDLE_TXN)
            ->assertOk()
            ->assertSee('https://cdn.paddle.com/paddle/v2/paddle.js', escape: false)
            ->assertSee("Paddle.Environment.set('sandbox')", escape: false)
            ->assertSee('"test_fake"', escape: false)
            ->assertSee(json_encode('https://app.example.test/app?checkout=success&session_id='.PADDLE_TXN, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), escape: false);
    });

    it('is only there for the Paddle driver and a transaction id', function (array $config, string $query) {
        config($config);

        $this->get('/payments/paddle'.$query)->assertNotFound();
    })->with([
        'fake driver' => [['services.payments.driver' => 'fake'], '?_ptxn='.PADDLE_TXN],
        'no client token' => [['services.paddle.client_token' => null], '?_ptxn='.PADDLE_TXN],
        'no transaction' => [[], ''],
    ]);
});
