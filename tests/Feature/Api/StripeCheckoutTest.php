<?php

use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stripe\StripeClient;
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
        'services.stripe.secret' => 'sk_test_fake',
        'services.stripe.webhook_secret' => 'whsec_fake',
        'services.stripe.currency' => 'usd',
        'app.frontend_url' => 'https://app.example.test',
    ]);
    Http::preventStrayRequests();
    $this->user = User::factory()->create(['email' => 'owner@example.test']);
});

function stripeSessionCreated(string $id = 'cs_test_1'): array
{
    return [
        'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1']),
        'api.stripe.com/v1/coupons' => Http::response(['id' => 'co_1']),
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => $id, 'url' => "https://checkout.stripe.com/c/pay/{$id}", 'status' => 'open']),
    ];
}

/**
 * Posts an event to the webhook, signed with the test signing secret the way Stripe signs it.
 */
function sendStripeEvent(string $type, array $object, ?int $timestamp = null)
{
    $payload = json_encode(['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => $object]]);

    return test()->call('POST', '/api/v1/webhooks/stripe', server: [
        'HTTP_STRIPE_SIGNATURE' => StripeClient::signature($payload, $timestamp),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload);
}

function openSession(User $user, string $plan = 'pro', array $attributes = []): CheckoutSession
{
    return CheckoutSession::factory()->for($user)->create([
        'plan_id' => Plan::where('slug', $plan)->value('id'),
        'stripe_session_id' => 'cs_test_1',
        ...$attributes,
    ]);
}

describe('POST /api/v1/checkout/session', function () {
    it('opens a Stripe payment page priced from the plans table, with the coupon off the first payment', function () {
        Http::fake(stripeSessionCreated());

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro', 'billing' => 'annual', 'coupon' => 'blex20'])
            ->assertCreated()
            ->assertJsonPath('data.id', 'cs_test_1')
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test_1')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.coupon', 'BLEX20')
            // $29 a month billed yearly is $348; 20% off each month is $5.80, so $69.60 off: $278.40.
            ->assertJsonPath('data.amount_total', 278.4);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/coupons'
            && $request['amount_off'] === 6960 && $request['duration'] === 'once' && $request['currency'] === 'usd');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request->hasHeader('Authorization', 'Bearer sk_test_fake')
            && $request['mode'] === 'subscription'
            && $request['customer'] === 'cus_1'
            && $request['line_items'][0]['price_data']['unit_amount'] === 34800
            && $request['line_items'][0]['price_data']['recurring']['interval'] === 'year'
            && $request['discounts'][0]['coupon'] === 'co_1'
            && $request['success_url'] === 'https://app.example.test/app?checkout=success&session_id={CHECKOUT_SESSION_ID}'
            && $request['cancel_url'] === 'https://app.example.test/app?checkout=cancelled');

        expect($this->user->fresh()->stripe_customer_id)->toBe('cus_1')
            ->and(CheckoutSession::sole())->amount_total->toBe(27840)->status->toBe('open');
    });

    it('reuses the Stripe customer and sends no coupon without a code', function () {
        $this->user->update(['stripe_customer_id' => 'cus_existing']);
        Http::fake(stripeSessionCreated());

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'business'])
            ->assertCreated()
            ->assertJsonPath('data.amount_total', 99);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['customer'] === 'cus_existing'
            && $request['line_items'][0]['price_data']['unit_amount'] === 9900
            && $request['line_items'][0]['price_data']['recurring']['interval'] === 'month'
            && ! isset($request['discounts']));
    });

    it('takes a coupon on the trial plan once it is paid for', function () {
        Subscription::factory()->for($this->user)->trialEnded()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);
        Http::fake(stripeSessionCreated());

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'essential', 'coupon' => 'BLEX20'])
            ->assertCreated()
            ->assertJsonPath('data.amount_total', 13.6);
    });

    it('refuses a coupon that does not apply before calling Stripe', function () {
        Coupon::where('code', 'BLEX20')->update(['active' => false]);
        Http::fake();

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session?lang=ar', ['plan' => 'pro', 'coupon' => 'BLEX20'])
            ->assertUnprocessable()
            ->assertExactJson(['code' => 'coupon_invalid', 'message' => 'هذا الرمز غير صالح']);

        Http::assertNothingSent();
    });

    it('refuses Free, which needs no payment page', function () {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'free'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    });

    it('refuses a second payment for the plan already paid by card', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'stripe_subscription_id' => 'sub_1']);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])
            ->assertConflict()
            ->assertJsonPath('code', 'plan_already_paid');
    });

    it('says card payments are unavailable without a Stripe key', function () {
        config(['services.stripe.secret' => null]);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])
            ->assertServiceUnavailable()
            ->assertExactJson(['code' => 'payments_unavailable', 'message' => __('checkout.payments_unavailable')]);
    });

    it('says so when Stripe refuses, and keeps nothing', function () {
        Http::fake(['api.stripe.com/v1/customers' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'payment_provider_error');

        expect(CheckoutSession::count())->toBe(0);
    });

    it('needs a signed-in owner', function () {
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])->assertUnauthorized();
    });
});

describe('POST /api/v1/onboarding/plan with Stripe set up', function () {
    it('answers a paid plan with the payment page instead of payment_required', function () {
        Http::fake(stripeSessionCreated());

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'pro'])
            ->assertAccepted()
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test_1')
            ->assertJsonPath('data.plan', 'pro');

        expect($this->user->subscription()->exists())->toBeFalse();
    });

    it('sends the trial plan to payment once the account has had its trial', function () {
        Subscription::factory()->for($this->user)->trialEnded()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);
        Http::fake(stripeSessionCreated());

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'essential', 'billing' => 'annual'])
            ->assertAccepted()
            ->assertJsonPath('data.amount_total', 168);
    });

    it('still starts the first trial with no card', function () {
        Http::fake();

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'essential'])
            ->assertOk()
            ->assertJsonPath('data.status', 'trialing');

        Http::assertNothingSent();
    });

    it('stops the card payments when the owner moves to Free', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'stripe_subscription_id' => 'sub_1']);
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'status' => 'canceled'])]);

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'free'])
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'free')
            ->assertJsonPath('data.paid_by_card', false);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === 'https://api.stripe.com/v1/subscriptions/sub_1');
    });

    it('keeps the card plan when Stripe cannot stop its payments', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'stripe_subscription_id' => 'sub_1']);
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::response([], 500)]);

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'free'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'payment_provider_error');

        expect($this->user->subscription->fresh()->plan->slug)->toBe('pro');
    });
});

it('lets the coupon check price the trial plan once the trial was used', function () {
    Subscription::factory()->for($this->user)->trialEnded()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);

    Sanctum::actingAs($this->user);
    $this->postJson('/api/v1/coupons/check', ['code' => 'BLEX20', 'plan' => 'essential'])
        ->assertOk()
        ->assertJsonPath('data.price', 13.6);
});

describe('POST /api/v1/webhooks/stripe', function () {
    it('refuses an event without a valid signature', function (string $header) {
        $this->call('POST', '/api/v1/webhooks/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $header], content: '{"type":"invoice.paid"}')
            ->assertBadRequest();
    })->with([
        'no header' => [''],
        'wrong signature' => ['t='.time().',v1=deadbeef'],
    ]);

    it('refuses a signed event older than the tolerance', function () {
        sendStripeEvent('customer.subscription.deleted', ['id' => 'sub_1'], timestamp: time() - 301)->assertBadRequest();
    });

    it('puts the paid plan in place once, however often Stripe reports it', function () {
        $this->freezeTime();
        Subscription::factory()->for($this->user)->trialEnded()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);
        openSession($this->user, 'pro', ['billing_period' => 'annual', 'coupon_id' => Coupon::where('code', 'BLEX20')->value('id')]);
        $periodEnd = now()->addYear()->startOfSecond();
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'items' => ['data' => [['current_period_end' => $periodEnd->timestamp]]]])]);

        $completed = ['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_1', 'customer' => 'cus_1'];
        sendStripeEvent('checkout.session.completed', $completed)->assertOk();
        sendStripeEvent('checkout.session.completed', $completed)->assertOk();

        $subscription = $this->user->subscription->fresh();
        expect($subscription)
            ->plan->slug->toBe('pro')
            ->billing_period->toBe('annual')
            ->status->toBe('active')
            ->trial_ends_at->toBeNull()
            ->trial_used_at->not->toBeNull()
            ->stripe_subscription_id->toBe('sub_1')
            ->and($subscription->current_period_end->equalTo($periodEnd))->toBeTrue()
            ->and(CheckoutSession::sole()->status)->toBe('complete')
            ->and(Coupon::where('code', 'BLEX20')->value('times_used'))->toBe(1);
        Http::assertSentCount(1);
    });

    it('cancels the card plan the new one replaces', function () {
        Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'stripe_subscription_id' => 'sub_old']);
        openSession($this->user, 'business');
        Http::fake([
            'api.stripe.com/v1/subscriptions/sub_new' => Http::response(['id' => 'sub_new', 'current_period_end' => now()->addMonth()->timestamp]),
            'api.stripe.com/v1/subscriptions/sub_old' => Http::response(['id' => 'sub_old', 'status' => 'canceled']),
        ]);

        sendStripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_new'])->assertOk();

        expect($this->user->subscription->fresh())->plan->slug->toBe('business')->stripe_subscription_id->toBe('sub_new');
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/subscriptions/sub_old'));
    });

    it('leaves an unpaid session open, and marks an expired one', function () {
        openSession($this->user);

        sendStripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'unpaid', 'subscription' => 'sub_1'])->assertOk();
        expect(CheckoutSession::sole()->status)->toBe('open')
            ->and($this->user->subscription()->exists())->toBeFalse();

        sendStripeEvent('checkout.session.expired', ['id' => 'cs_test_1', 'status' => 'expired'])->assertOk();
        expect(CheckoutSession::sole()->status)->toBe('expired');
    });

    it('moves the paid period on when a renewal is paid, never back', function () {
        $subscription = Subscription::factory()->for($this->user)->create([
            'plan_id' => Plan::where('slug', 'pro')->value('id'),
            'stripe_subscription_id' => 'sub_1',
            'current_period_end' => now()->addDay(),
        ]);
        $renewedTo = now()->addMonth()->addDay()->startOfSecond();
        $invoice = fn (int $end) => ['id' => 'in_1', 'customer' => 'cus_1', 'parent' => ['subscription_details' => ['subscription' => 'sub_1']], 'lines' => ['data' => [['period' => ['end' => $end]]]]];

        sendStripeEvent('invoice.paid', $invoice($renewedTo->timestamp))->assertOk();
        sendStripeEvent('invoice.paid', $invoice(now()->addDay()->timestamp))->assertOk();

        expect($subscription->fresh()->current_period_end->equalTo($renewedTo))->toBeTrue();
    });

    it('completes the session when the first invoice arrives before checkout.session.completed', function () {
        $this->user->update(['stripe_customer_id' => 'cus_1']);
        openSession($this->user);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_1']),
            'api.stripe.com/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'current_period_end' => now()->addMonth()->timestamp]),
        ]);

        sendStripeEvent('invoice.paid', ['id' => 'in_1', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'lines' => ['data' => []]])->assertOk();

        expect($this->user->subscription->fresh())->plan->slug->toBe('pro')->stripe_subscription_id->toBe('sub_1');
    });

    it('drops the account to Free when the card plan ends, and ignores a plan already replaced', function () {
        $subscription = Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'stripe_subscription_id' => 'sub_1', 'current_period_end' => now()->addWeek()]);

        sendStripeEvent('customer.subscription.deleted', ['id' => 'sub_other'])->assertOk();
        expect($subscription->fresh()->plan->slug)->toBe('pro');

        sendStripeEvent('customer.subscription.deleted', ['id' => 'sub_1'])->assertOk();
        expect($subscription->fresh())
            ->plan->slug->toBe('free')
            ->stripe_subscription_id->toBeNull()
            ->current_period_end->toBeNull();
    });
});

describe('GET /api/v1/checkout/session/{id}', function () {
    it('asks Stripe while the webhook has not arrived, and shows the plan once paid', function () {
        openSession($this->user);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_1']),
            'api.stripe.com/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'current_period_end' => now()->addMonth()->timestamp]),
        ]);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/checkout/session/cs_test_1')
            ->assertOk()
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonPath('data.subscription.plan.id', 'pro')
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.paid_by_card', true);
    });

    it('answers "not yet" when Stripe cannot be reached', function () {
        openSession($this->user);
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response([], 500)]);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/checkout/session/cs_test_1')
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonMissingPath('data.subscription');
    });

    it('hides another account’s session', function () {
        openSession(User::factory()->create());

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/checkout/session/cs_test_1')->assertNotFound();
    });
});
