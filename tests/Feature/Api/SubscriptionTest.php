<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OnboardingSeeder::class);
    $this->user = User::factory()->create();
});

function subscribeOwner(User $user, string $plan, string $factoryState = 'active', string $billing = 'monthly'): Subscription
{
    $factory = Subscription::factory()->for($user);

    if ($factoryState !== 'active') {
        $factory = $factory->{$factoryState}();
    }

    return $factory->create(['plan_id' => Plan::where('slug', $plan)->value('id'), 'billing_period' => $billing]);
}

it('requires a token', function (string $method) {
    $this->json($method, '/api/v1/subscription')->assertUnauthorized();
})->with(['GET', 'POST', 'DELETE']);

describe('GET /api/v1/subscription', function () {
    it('returns the current plan as the onboarding does', function () {
        subscribeOwner($this->user, 'essential', 'trialing', 'annual');

        $this->actingAs($this->user)->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'essential')
            ->assertJsonPath('data.billing_period', 'annual')
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.trial_days_left', 14);
    });

    it('returns null before a plan is chosen', function () {
        $this->actingAs($this->user)->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    });
});

describe('POST /api/v1/subscription', function () {
    it('moves to Free at once, ending the trial', function () {
        subscribeOwner($this->user, 'essential', 'trialing');

        $this->actingAs($this->user)->postJson('/api/v1/subscription', ['plan' => 'free'])
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'free')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.trial_ends_at', null);
    });

    it('starts the trial of a plan sold with one', function () {
        subscribeOwner($this->user, 'free');

        $this->actingAs($this->user)->postJson('/api/v1/subscription', ['plan' => 'essential'])
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'essential')
            ->assertJsonPath('data.status', 'trialing');
    });

    it('switches the billing period alone, keeping the plan and its trial clock', function () {
        $subscription = subscribeOwner($this->user, 'essential', 'trialing');
        $endsAt = $subscription->trial_ends_at->toIso8601String();
        $this->travel(2)->days();

        $this->actingAs($this->user)->postJson('/api/v1/subscription', ['billing' => 'annual'])
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'essential')
            ->assertJsonPath('data.billing_period', 'annual')
            ->assertJsonPath('data.trial_ends_at', $endsAt);
    });

    it('keeps the current billing period when only the plan changes', function () {
        subscribeOwner($this->user, 'free', billing: 'annual');

        $this->actingAs($this->user)->postJson('/api/v1/subscription', ['plan' => 'essential'])
            ->assertJsonPath('data.billing_period', 'annual');
    });

    it('refuses a second trial', function () {
        subscribeOwner($this->user, 'essential', 'trialEnded');

        $this->actingAs($this->user)->postJson('/api/v1/subscription?lang=ar', ['plan' => 'essential'])
            ->assertStatus(409)
            ->assertExactJson(['code' => 'trial_used', 'message' => __('subscription.trial_used', locale: 'ar')]);
    });

    it('asks for payment on a paid plan without a trial', function () {
        subscribeOwner($this->user, 'free');

        $this->actingAs($this->user)->postJson('/api/v1/subscription', ['plan' => 'pro'])
            ->assertStatus(402)
            ->assertExactJson(['code' => 'payment_required', 'message' => __('subscription.payment_required')]);

        expect($this->user->fresh()->subscription->plan->slug)->toBe('free');
    });

    it('refuses a body it cannot apply', function (array $body, string $field) {
        $this->actingAs($this->user)->postJson('/api/v1/subscription', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'nothing' => [[], 'plan'],
        'an unknown plan' => [['plan' => 'gold'], 'plan'],
        'an unknown billing period' => [['billing' => 'weekly'], 'billing'],
        'a billing period with no plan chosen yet' => [['billing' => 'annual'], 'plan'],
    ]);
});

describe('DELETE /api/v1/subscription', function () {
    it('cancels back to Free, keeping the billing period and the used trial', function () {
        subscribeOwner($this->user, 'essential', 'trialing', 'annual');

        $this->actingAs($this->user)->deleteJson('/api/v1/subscription?lang=ar')
            ->assertOk()
            ->assertJsonPath('message', 'أنت الآن على الخطة المجانية.')
            ->assertJsonPath('data.plan.id', 'free')
            ->assertJsonPath('data.billing_period', 'annual')
            ->assertJsonPath('data.status', 'active');

        $this->postJson('/api/v1/subscription', ['plan' => 'essential'])->assertStatus(409);
    });

    it('puts an account with no plan yet on Free', function () {
        $this->actingAs($this->user)->deleteJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'free')
            ->assertJsonPath('data.billing_period', 'monthly');
    });
});
