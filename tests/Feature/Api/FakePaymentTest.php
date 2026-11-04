<?php

use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\CheckoutSeeder;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, CheckoutSeeder::class]);
    config([
        'services.payments.driver' => 'fake',
        'services.payments.allow_fake' => false,
        'services.paddle.api_key' => null,
        'app.frontend_url' => 'https://app.example.test',
    ]);
    Http::preventStrayRequests();
    $this->user = User::factory()->create(['email' => 'owner@example.test']);
});

/**
 * Opens a fake payment page for the plan through the API, and returns its session.
 */
function startFakeCheckout(User $user, array $body = ['plan' => 'pro']): CheckoutSession
{
    Sanctum::actingAs($user);
    test()->postJson('/api/v1/checkout/session', $body)->assertCreated();

    return CheckoutSession::sole();
}

function fakeAction(CheckoutSession $session, string $action): string
{
    return URL::temporarySignedRoute("payments.fake.{$action}", now()->addHour(), ['session' => $session->payment_session_id]);
}

it('opens a payment page on this app, with no keys and no network', function () {
    Sanctum::actingAs($this->user);
    $response = $this->postJson('/api/v1/checkout/session', ['plan' => 'pro', 'billing' => 'annual', 'coupon' => 'BLEX20'])
        ->assertCreated()
        ->assertJsonPath('data.amount_total', 278.4);

    expect($response->json('data.id'))->toStartWith('fake_cs_')
        ->and($response->json('data.url'))->toStartWith(url('/payments/fake/'.$response->json('data.id')));

    $this->get($response->json('data.url'))
        ->assertOk()
        ->assertSeeText(['Pro', 'Annually (12 months)', 'BLEX20 — 20% off the first payment', '$278.40', 'Then $348 every year until cancelled.', 'Pay (test)', 'Decline']);
    Http::assertNothingSent();
});

it('shows the page in Arabic with ?lang=ar, keeping the signature', function () {
    $session = startFakeCheckout($this->user);

    $this->get(str_replace('lang=en', 'lang=ar', $session->url))
        ->assertOk()
        ->assertSee('dir="rtl"', escape: false)
        ->assertSeeText(['الفوترة', 'شهرية', 'ادفع (اختبار)', 'رفض']);
});

it('activates the plan by the webhook’s path on "Pay (test)", then goes to the success page', function () {
    $this->freezeTime();
    $session = startFakeCheckout($this->user, ['plan' => 'business', 'billing' => 'annual', 'coupon' => 'BLEX20']);

    $this->post(fakeAction($session, 'pay'))
        ->assertRedirect("https://app.example.test/app?checkout=success&session_id={$session->payment_session_id}");

    expect($this->user->subscription->fresh())
        ->plan->slug->toBe('business')
        ->billing_period->toBe('annual')
        ->status->toBe('active')
        ->payment_subscription_id->toStartWith('fake_sub_')
        ->current_period_end->toDateTimeString()->toBe(now()->addYear()->toDateTimeString())
        ->and(Coupon::where('code', 'BLEX20')->value('times_used'))->toBe(1);

    Sanctum::actingAs($this->user);
    $this->getJson("/api/v1/checkout/session/{$session->payment_session_id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'complete')
        ->assertJsonPath('data.subscription.plan.id', 'business')
        ->assertJsonPath('data.subscription.paid_by_card', true);
    Http::assertNothingSent();
});

it('pays once when "Pay (test)" is pressed twice', function () {
    $session = startFakeCheckout($this->user, ['plan' => 'pro', 'coupon' => 'BLEX20']);

    $this->post(fakeAction($session, 'pay'))->assertRedirect();
    $subscriptionId = $this->user->subscription->fresh()->payment_subscription_id;
    $this->post(fakeAction($session, 'pay'))->assertRedirect();

    expect($this->user->subscription->fresh()->payment_subscription_id)->toBe($subscriptionId)
        ->and(Coupon::where('code', 'BLEX20')->value('times_used'))->toBe(1);
});

it('goes back to the app with nothing charged on "Decline"', function () {
    $session = startFakeCheckout($this->user);

    $this->post(fakeAction($session, 'decline'))->assertRedirect('https://app.example.test/app?checkout=cancelled');

    expect($this->user->subscription()->exists())->toBeFalse()
        ->and($session->fresh()->status)->toBe('open');
});

it('refuses a link without a valid signature', function (string $method, string $action) {
    $session = startFakeCheckout($this->user);
    $signed = $action === 'show' ? $session->url : fakeAction($session, $action);

    $this->call($method, preg_replace('/signature=\w+/', 'signature=forged', $signed))->assertForbidden();
    $this->call($method, url("/payments/fake/{$session->payment_session_id}".($action === 'show' ? '' : "/{$action}")))->assertForbidden();

    expect($this->user->subscription()->exists())->toBeFalse();
})->with([
    'the page' => ['GET', 'show'],
    'Pay' => ['POST', 'pay'],
    'Decline' => ['POST', 'decline'],
]);

it('cancels a fake-paid plan back to Free without a provider', function () {
    Subscription::factory()->for($this->user)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'payment_provider' => 'fake', 'payment_subscription_id' => 'fake_sub_1']);

    $this->actingAs($this->user)->deleteJson('/api/v1/subscription')
        ->assertOk()
        ->assertJsonPath('data.plan.id', 'free')
        ->assertJsonPath('data.paid_by_card', false);
    Http::assertNothingSent();
});

describe('in production', function () {
    beforeEach(function () {
        $this->app['env'] = 'production';
        // CSRF is only skipped while the environment is "testing".
        $this->withoutMiddleware(PreventRequestForgery::class);
    });

    it('refuses the fake driver', function () {
        $session = CheckoutSession::factory()->for($this->user)->create(['payment_provider' => 'fake', 'payment_session_id' => 'fake_cs_1']);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/checkout/session', ['plan' => 'pro'])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'payments_unavailable');
        $this->post(fakeAction($session, 'pay'))->assertNotFound();
        $this->postJson('/api/v1/onboarding/plan', ['plan' => 'pro'])->assertStatus(402);
    });

    it('allows it on a development server with PAYMENT_ALLOW_FAKE', function () {
        config(['services.payments.allow_fake' => true]);

        $session = startFakeCheckout($this->user);
        $this->post(fakeAction($session, 'pay'))->assertRedirect();

        expect($this->user->subscription->fresh()->plan->slug)->toBe('pro');
    });
});
