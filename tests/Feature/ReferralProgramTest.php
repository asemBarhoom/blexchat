<?php

use App\Filament\Pages\ManageReferralProgram;
use App\Filament\Pages\ReferredSignUps;
use App\Filament\Resources\ReferralCommissionResource\Pages\ListReferralCommissions;
use App\Mail\LoginLinkMail;
use App\Models\CheckoutSession;
use App\Models\HomeBlock;
use App\Models\Plan;
use App\Models\ReferralCommission;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Referrals\ReferralProgram;
use App\Services\Stripe\StripeClient;
use Database\Seeders\CheckoutSeeder;
use Database\Seeders\HomeContentSeeder;
use Database\Seeders\OnboardingSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, CheckoutSeeder::class, HomeContentSeeder::class]);
    config([
        'services.stripe.secret' => 'sk_test_fake',
        'services.stripe.webhook_secret' => 'whsec_fake',
        'services.payments.driver' => 'stripe',
        'services.stripe.currency' => 'usd',
        'app.frontend_url' => 'https://blexchat.test',
    ]);
    Mail::fake();
    Http::preventStrayRequests();
    Setting::put(Setting::REFERRAL_ENABLED, true);

    $this->referrer = User::factory()->create(['email' => 'creator@example.test']);
    $this->code = $this->referrer->referralCode();
});

/**
 * Signs up through the emailed link, as the site does, and returns the account.
 */
function signUpWithEmailLink(string $email, array $send = [], array $verify = []): User
{
    test()->postJson('/api/v1/auth/email-link', ['email' => $email, ...$send])->assertAccepted();
    $token = Mail::sent(LoginLinkMail::class)->last()->token;
    test()->postJson('/api/v1/auth/email-link/verify', ['token' => $token, ...$verify])->assertOk();

    return User::where('email', $email)->firstOrFail();
}

function postStripeEvent(string $type, array $object)
{
    $payload = json_encode(['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => $object]]);

    return test()->call('POST', '/api/v1/webhooks/stripe', server: [
        'HTTP_STRIPE_SIGNATURE' => StripeClient::signature($payload),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload)->assertOk();
}

/**
 * A referred customer pays for Pro by card: $29, the session's first payment.
 */
function payFirstPayment(User $customer, string $sessionId = 'cs_test_1', string $subscriptionId = 'sub_1'): void
{
    CheckoutSession::factory()->for($customer)->create([
        'plan_id' => Plan::where('slug', 'pro')->value('id'),
        'stripe_session_id' => $sessionId,
        'amount_total' => 2900,
    ]);
    Http::fake(["api.stripe.com/v1/subscriptions/{$subscriptionId}" => Http::response(['id' => $subscriptionId, 'current_period_end' => now()->addMonth()->timestamp])]);

    postStripeEvent('checkout.session.completed', ['id' => $sessionId, 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => $subscriptionId]);
}

function payRenewal(string $invoiceId, int $cents = 2900, string $reason = 'subscription_cycle'): void
{
    postStripeEvent('invoice.paid', [
        'id' => $invoiceId,
        'billing_reason' => $reason,
        'amount_paid' => $cents,
        'currency' => 'usd',
        'subscription' => 'sub_1',
        'lines' => ['data' => [['period' => ['end' => now()->addMonths(2)->timestamp]]]],
    ]);
}

describe('signing up through a referral link', function () {
    it('gives the new account to the referrer, even when the link is opened on another device', function () {
        $customer = signUpWithEmailLink('shop@example.test', send: ['ref' => strtolower($this->code)]);

        expect($customer->referrer->is($this->referrer))->toBeTrue()
            ->and($customer->referred_at)->not->toBeNull();
    });

    it('takes the code when finishing sign-up if the link was sent without one', function () {
        $customer = signUpWithEmailLink('shop@example.test', verify: ['ref' => $this->code]);

        expect($customer->referred_by_user_id)->toBe($this->referrer->id);
    });

    it('never moves an existing account to a referrer', function () {
        $existing = User::factory()->create(['email' => 'shop@example.test']);

        signUpWithEmailLink('shop@example.test', send: ['ref' => $this->code]);

        expect($existing->fresh()->referred_by_user_id)->toBeNull();
    });

    it('ignores an unknown code, and every code while the program is closed, without refusing the sign-up', function () {
        expect(signUpWithEmailLink('first@example.test', send: ['ref' => 'NOSUCH99'])->referred_by_user_id)->toBeNull();

        Setting::put(Setting::REFERRAL_ENABLED, false);

        expect(signUpWithEmailLink('second@example.test', send: ['ref' => $this->code])->referred_by_user_id)->toBeNull();
    });

    it('carries the code through Google sign-in', function () {
        config(['services.google.client_id' => 'client-123']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-7', 'email' => 'sara@gmail.com', 'email_verified' => true, 'name' => 'Sara']),
        ]);

        $url = $this->getJson('/api/v1/auth/google?ref='.$this->code)->assertOk()->json('data.url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->postJson('/api/v1/auth/google', ['code' => 'auth-code', 'state' => $query['state']])->assertOk();

        expect(User::where('email', 'sara@gmail.com')->sole()->referred_by_user_id)->toBe($this->referrer->id);
    });

    it('never gives an account to itself', function () {
        expect(app(ReferralProgram::class)->attribute($this->referrer, $this->code))->toBeFalse()
            ->and($this->referrer->fresh()->referred_by_user_id)->toBeNull();
    });
});

describe('commissions', function () {
    beforeEach(function () {
        $this->customer = User::factory()->create(['referred_by_user_id' => $this->referrer->id]);
    });

    it('earns the rate on the first payment once, however often Stripe reports it', function () {
        payFirstPayment($this->customer);
        postStripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_1']);
        // The first invoice belongs to the same payment.
        payRenewal('in_first', reason: 'subscription_create');

        expect(ReferralCommission::sole())
            ->referrer_id->toBe($this->referrer->id)
            ->referred_user_id->toBe($this->customer->id)
            ->payment_cents->toBe(2900)
            ->rate->toBe(20.0)
            ->amount_cents->toBe(580)
            ->status->toBe(ReferralCommission::PENDING);
    });

    it('earns nothing on renewals with first-payment commission', function () {
        payFirstPayment($this->customer);
        payRenewal('in_2');

        expect(ReferralCommission::count())->toBe(1);
    });

    it('earns on renewals within the months set, at the rate of the day, once per invoice', function () {
        Setting::put(Setting::REFERRAL_COMMISSION, ReferralProgram::RECURRING);
        Setting::put(Setting::REFERRAL_MONTHS, 2);
        payFirstPayment($this->customer);

        Setting::put(Setting::REFERRAL_RATE, 30);
        $this->travel(1)->months();
        payRenewal('in_2');
        payRenewal('in_2');

        $this->travel(1)->months();
        payRenewal('in_3');

        expect(ReferralCommission::orderBy('id')->pluck('amount_cents')->all())->toBe([580, 870])
            ->and(ReferralCommission::latest('id')->first()->rate)->toBe(30.0);
    });

    it('earns on every renewal when the months are 0', function () {
        Setting::put(Setting::REFERRAL_COMMISSION, ReferralProgram::RECURRING);
        Setting::put(Setting::REFERRAL_MONTHS, 0);
        payFirstPayment($this->customer);

        $this->travel(3)->years();
        payRenewal('in_later');

        expect(ReferralCommission::count())->toBe(2);
    });

    it('earns nothing while the program is closed, or for a customer nobody referred', function () {
        Setting::put(Setting::REFERRAL_ENABLED, false);
        payFirstPayment($this->customer);

        Setting::put(Setting::REFERRAL_ENABLED, true);
        payFirstPayment(User::factory()->create(), 'cs_test_2', 'sub_2');

        expect(ReferralCommission::count())->toBe(0)
            ->and(Subscription::count())->toBe(2);
    });
});

it('shows the owner their link, the rules and what they earned', function () {
    $paying = User::factory()->create(['referred_by_user_id' => $this->referrer->id]);
    Subscription::factory()->for($paying)->create(['plan_id' => Plan::where('slug', 'pro')->value('id'), 'status' => 'active', 'stripe_subscription_id' => 'sub_9']);
    User::factory()->create(['referred_by_user_id' => $this->referrer->id]);
    ReferralCommission::factory()->for($this->referrer, 'referrer')->create(['referred_user_id' => $paying->id, 'amount_cents' => 580]);
    ReferralCommission::factory()->paid()->for($this->referrer, 'referrer')->create(['referred_user_id' => $paying->id, 'amount_cents' => 1000]);
    ReferralCommission::factory()->create(['amount_cents' => 999]);

    Sanctum::actingAs($this->referrer);

    $this->getJson('/api/v1/referrals?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.code', $this->code)
        ->assertJsonPath('data.link', 'https://blexchat.test/?ref='.$this->code)
        ->assertJsonPath('data.rate', 20)
        ->assertJsonPath('data.terms', '20% من أول دفعة يدفعها')
        ->assertJsonPath('data.commission', ['type' => 'first_payment', 'months' => null])
        ->assertJsonPath('data.payout_method', 'تُحوَّل الأرباح بتحويل بنكي في بداية كل شهر.')
        ->assertJsonPath('data.stats', ['signed_up' => 2, 'subscribed' => 1, 'earned' => 15.8, 'pending' => 5.8, 'paid_out' => 10])
        ->assertJsonCount(2, 'data.commissions')
        ->assertJsonPath('data.commissions.0.status', 'paid');
});

describe('the public page', function () {
    it('is not there while the program is closed', function () {
        Setting::put(Setting::REFERRAL_ENABLED, false);

        $this->getJson('/api/v1/referral-program')->assertNotFound();
    });

    it('fills the rules into its copy, in each language', function () {
        Setting::put(Setting::REFERRAL_COMMISSION, ReferralProgram::RECURRING);
        Setting::put(Setting::REFERRAL_MONTHS, 12);

        $english = $this->getJson('/api/v1/referral-program')->assertOk();
        $pro = collect($english->json('data.earnings'))->firstWhere('plan', 'pro');
        $upTo = collect($english->json('data.earnings'))->max('annual');

        expect($english->json('data.lead'))->toContain('you earn 20% of every payment for 12 months.')
            ->and($english->json('data.title'))->toBe('Earn up to $'.number_format($upTo, 2).' for every customer you bring')
            ->and($english->json('data.faq.2.answer'))->toBe('Earnings are paid by bank transfer at the start of each month.')
            ->and($english->json('data.cta'))->toBe(['label' => 'Get your link', 'to' => '/signup'])
            ->and($pro['monthly'])->toEqual(round(Plan::where('slug', 'pro')->value('monthly_price') * 0.2, 2));

        $this->getJson('/api/v1/referral-program?lang=ar')
            ->assertOk()
            ->assertJsonPath('meta.fallback', false)
            ->assertJsonPath('data.steps.1.text', 'ضعه في البايو أو القصص أو الرسائل. الزيارة من خلاله تُحسب لمدة 30 يومًا.')
            ->assertJsonPath('data.benefits.0.title', 'عمولة 20%')
            ->assertJsonPath('data.terms', '20% من كل دفعة لمدة 12 شهرًا');
    });
});

it('links the page from the header and footer only while the program is open', function () {
    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.referral_program_enabled', true)
        ->assertJsonPath('data.navigation.items.4', ['id' => 'referral', 'label' => 'Referral program', 'to' => '/referral'])
        ->assertJsonPath('data.footer.columns.2.links.2', ['label' => 'Referral program', 'to' => '/referral']);

    Setting::put(Setting::REFERRAL_ENABLED, false);

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.referral_program_enabled', false)
        ->assertJsonCount(4, 'data.navigation.items')
        ->assertJsonCount(2, 'data.footer.columns.2.links');
});

describe('the admin', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('sets the rules and the copy, and a cleared Arabic text falls back to English', function () {
        Livewire::test(ManageReferralProgram::class)
            ->assertFormSet(['enabled' => true, 'rate' => 20, 'texts.ar.cta.label' => 'احصل على رابطك'])
            ->fillForm([
                'rate' => '12.5',
                'commission' => ReferralProgram::RECURRING,
                'months' => 6,
                'cookie_days' => 60,
                'texts.en.title' => 'Earn {rate}% on every sale',
                'texts.ar.title' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Setting::get(Setting::REFERRAL_RATE))->toBe(12.5)
            ->and(app(ReferralProgram::class)->commission())->toBe(['type' => 'recurring', 'months' => 6])
            ->and(app(ReferralProgram::class)->cookieDays())->toBe(60);

        $this->getJson('/api/v1/referral-program?lang=ar')
            ->assertJsonPath('data.title', 'Earn 12.5% on every sale')
            ->assertJsonPath('meta.fallback', true);
    });

    it('marks a commission paid once the money is sent', function () {
        $commission = ReferralCommission::factory()->create();

        Livewire::test(ListReferralCommissions::class)
            ->assertCanSeeTableRecords([$commission])
            ->callAction(TestAction::make('markPaid')->table($commission));

        expect($commission->fresh())->status->toBe(ReferralCommission::PAID)->paid_at->not->toBeNull();
    });

    it('lists who signed up through whose link', function () {
        $referred = User::factory()->create(['referred_by_user_id' => $this->referrer->id, 'referred_at' => now()]);

        Livewire::test(ReferredSignUps::class)->assertCanSeeTableRecords([$referred])->assertCanNotSeeTableRecords([$this->referrer]);
    });

    it('opens the referral screens', function () {
        $this->get('/admin/referral-program')->assertOk();
        $this->get('/admin/referral-commissions')->assertOk();
        $this->get('/admin/referrals')->assertOk();
    });
});

it('adds the copy and the links to a homepage seeded before the program', function () {
    HomeBlock::where('key', ReferralProgram::BLOCK)->delete();
    $navigation = HomeBlock::where('key', 'navigation')->sole();
    $settings = $navigation->settings;
    array_pop($settings['items']);
    $texts = $navigation->getTranslations('texts');
    array_pop($texts['en']['items']);
    array_pop($texts['ar']['items']);
    $navigation->forceFill(['settings' => $settings])->replaceTranslations('texts', $texts)->save();

    $migration = require database_path('migrations/2026_11_24_143126_add_referral_program_content.php');
    $migration->up();
    $migration->up();

    $this->getJson('/api/v1/home?lang=ar')
        ->assertJsonCount(5, 'data.navigation.items')
        ->assertJsonPath('data.navigation.items.4', ['id' => 'referral', 'label' => 'برنامج الإحالة', 'to' => '/referral'])
        ->assertJsonCount(3, 'data.footer.columns.2.links');
    $this->getJson('/api/v1/referral-program')->assertOk()->assertJsonPath('data.cta.to', '/signup');
});
