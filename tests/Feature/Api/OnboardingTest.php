<?php

use App\Models\Channel;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SurveyQuestion;
use App\Models\User;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OnboardingSeeder::class);
    $this->user = User::factory()->create();
});

describe('GET /api/v1/plans', function () {
    it('lists the plan ladder the frontend shows, in order, without signing in', function () {
        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonPath('data.*.id', ['free', 'essential', 'pro', 'business', 'advanced'])
            ->assertJsonPath('data.1', [
                'id' => 'essential',
                'name' => 'Essential',
                'tagline' => 'For a growing shop or creator.',
                'monthly_price' => 17,
                'annual_price' => 14,
                'annual_total' => 168,
                'currency' => 'USD',
                'contacts_limit' => 250,
                'has_ai' => false,
                'features' => ['All four channels', 'Unlimited automations', 'No Blexchat branding'],
                'trial_days' => 14,
                'is_free' => false,
                'is_recommended' => true,
            ]);
    });

    it('answers in Arabic with ?lang=ar', function () {
        $this->getJson('/api/v1/plans?lang=ar')
            ->assertJsonPath('data.0.name', 'مجانية')
            ->assertJsonPath('data.0.features.0', 'قناة واحدة');
    });

    it('hides plans taken off sale', function () {
        Plan::where('slug', 'advanced')->update(['active' => false]);

        $this->getJson('/api/v1/plans')->assertJsonCount(4, 'data');
    });
});

describe('GET /api/v1/onboarding', function () {
    it('needs a signed-in owner', function (string $method, string $uri) {
        $this->json($method, $uri)->assertUnauthorized();
    })->with([
        ['GET', '/api/v1/onboarding'],
        ['GET', '/api/v1/onboarding/survey'],
        ['POST', '/api/v1/onboarding/survey'],
        ['POST', '/api/v1/onboarding/plan'],
    ]);

    it('walks a new owner through channel, survey and plan, in the frontend’s order', function () {
        $this->actingAs($this->user)->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.next_step', 'channel')
            ->assertJsonPath('data.channels', [])
            ->assertJsonPath('data.survey_completed_at', null)
            ->assertJsonPath('data.subscription', null);

        Channel::factory()->for($this->user)->create(['platform' => 'instagram']);
        $this->getJson('/api/v1/onboarding')->assertJsonPath('data.next_step', 'survey')->assertJsonCount(1, 'data.channels');

        $this->postJson('/api/v1/onboarding/survey', ['answers' => []])->assertOk();
        $this->getJson('/api/v1/onboarding')->assertJsonPath('data.next_step', 'plan');

        $this->postJson('/api/v1/onboarding/plan', ['plan' => 'free'])->assertOk();
        $this->getJson('/api/v1/onboarding')
            ->assertJsonPath('data.next_step', 'done')
            ->assertJsonPath('data.subscription.plan.id', 'free');
    });
});

describe('the survey', function () {
    it('lists the four steps with their options and no answers yet', function () {
        $this->actingAs($this->user)->getJson('/api/v1/onboarding/survey')
            ->assertOk()
            ->assertJsonPath('data.*.id', ['for', 'money', 'tools', 'heard'])
            ->assertJsonPath('data.0.kind', 'radio')
            ->assertJsonPath('data.0.options.1', ['id' => 'myself', 'label' => 'Myself', 'note' => 'My own brand, shop or creator account.', 'icon' => null, 'icon_url' => null, 'icon_box' => null])
            ->assertJsonPath('data.2.options.*.icon', ['shopify', 'salla', 'woocommerce', 'googlesheets', 'mailchimp', 'calendly', 'stripe', 'zapier'])
            ->assertJsonPath('data.2.options.2.icon_box', ['viewBox' => '0 9.58 24 4.84', 'width' => 64, 'height' => 13])
            ->assertJsonPath('data.3.options.*.icon', ['instagram', 'tiktok', 'snapchat', 'youtube', null, null, null])
            ->assertJsonPath('data.3.other_option', 'other')
            ->assertJsonPath('data.*.answer', [null, null, null, null]);
    });

    it('gives an uploaded logo as an absolute URL', function () {
        SurveyQuestion::where('key', 'heard')->firstOrFail()->options()->where('key', 'friend')->update(['icon_image' => 'survey/icons/friend.png']);

        $this->actingAs($this->user)->getJson('/api/v1/onboarding/survey')
            ->assertJsonPath('data.3.options.5.icon', null)
            ->assertJsonPath('data.3.options.5.icon_url', url('/storage/survey/icons/friend.png'));
    });

    it('saves the answers, keeps a skipped step empty, and shows them back', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/survey', ['answers' => [
            ['question' => 'for', 'options' => ['myself']],
            ['question' => 'heard', 'options' => ['instagram', 'other'], 'other' => 'A podcast about Gulf e-commerce'],
        ]])
            ->assertOk()
            ->assertJsonPath('data.0.answer', ['options' => ['myself'], 'other' => null])
            ->assertJsonPath('data.1.answer', null)
            ->assertJsonPath('data.3.answer', ['options' => ['instagram', 'other'], 'other' => 'A podcast about Gulf e-commerce']);

        expect($this->user->fresh()->survey_completed_at)->not->toBeNull();
    });

    it('replaces earlier answers when sent again', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/survey', ['answers' => [['question' => 'money', 'options' => ['products']]]]);
        $this->postJson('/api/v1/onboarding/survey', ['answers' => [['question' => 'tools', 'options' => ['salla', 'shopify']]]])->assertOk();

        expect($this->user->surveyAnswers()->with('question')->get()->map(fn ($answer) => [$answer->question->key, $answer->options])->all())
            ->toBe([['tools', ['salla', 'shopify']]]);
    });

    it('only shows each owner their own answers', function () {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/onboarding/survey', ['answers' => [['question' => 'for', 'options' => ['client']]]]);

        $this->actingAs($this->user)->getJson('/api/v1/onboarding/survey')->assertJsonPath('data.0.answer', null);
    });

    it('leaves out a hidden question', function () {
        SurveyQuestion::where('key', 'tools')->update(['active' => false]);

        $this->actingAs($this->user)->getJson('/api/v1/onboarding/survey')->assertJsonPath('data.*.id', ['for', 'money', 'heard']);
        $this->postJson('/api/v1/onboarding/survey', ['answers' => [['question' => 'tools', 'options' => ['salla']]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.0.question');
    });

    it('refuses answers that do not fit the question', function (array $answer, string $field) {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/survey', ['answers' => [$answer]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($this->user->surveyAnswers()->exists())->toBeFalse()
            ->and($this->user->fresh()->survey_completed_at)->toBeNull();
    })->with([
        'an unknown question' => [['question' => 'age', 'options' => ['30']], 'answers.0.question'],
        'no option picked' => [['question' => 'money', 'options' => []], 'answers.0.options'],
        'another question’s option' => [['question' => 'money', 'options' => ['shopify']], 'answers.0.options'],
        'two answers to a radio question' => [['question' => 'for', 'options' => ['myself', 'client']], 'answers.0.options'],
        'free text without its option' => [['question' => 'heard', 'options' => ['instagram'], 'other' => 'A podcast'], 'answers.0.other'],
    ]);

    it('explains a wrong answer in Arabic with ?lang=ar', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/survey?lang=ar', ['answers' => [['question' => 'for', 'options' => ['myself', 'client']]]])
            ->assertJsonValidationErrors(['answers.0.options' => 'اختر إجابة واحدة فقط لسؤال "لمن هذا الحساب؟".']);
    });

    it('needs the answers list, even when every step was skipped', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/survey', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers');
    });
});

describe('POST /api/v1/onboarding/plan', function () {
    it('puts the owner on Free at once', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'free'])
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'free')
            ->assertJsonPath('data.chosen_plan', 'free')
            ->assertJsonPath('data.billing_period', 'monthly')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.trial_ends_at', null)
            ->assertJsonPath('data.trial_days_left', null);
    });

    it('starts the free trial on the plan sold with one, with no card', function () {
        $this->freezeTime();

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'essential', 'billing' => 'annual'])
            ->assertOk()
            ->assertJsonPath('data.plan.id', 'essential')
            ->assertJsonPath('data.billing_period', 'annual')
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.trial_ends_at', now()->addDays(14)->toIso8601String())
            ->assertJsonPath('data.trial_days_left', 14);
    });

    it('asks for payment on a paid plan without a trial', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'pro'])
            ->assertStatus(402)
            ->assertExactJson(['code' => 'payment_required', 'message' => __('onboarding.plan.payment_required')]);

        expect($this->user->subscription()->exists())->toBeFalse();
    });

    it('gives each account one trial only', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'essential'])->assertOk();
        $this->postJson('/api/v1/onboarding/plan', ['plan' => 'free'])->assertOk();

        $this->postJson('/api/v1/onboarding/plan?lang=ar', ['plan' => 'essential'])
            ->assertConflict()
            ->assertJsonPath('code', 'trial_used')
            ->assertJsonPath('message', 'لقد استخدمت فترتك التجريبية من قبل. تابع بالخطة المجانية حالياً.');

        expect($this->user->fresh()->subscription->plan->slug)->toBe('free');
    });

    it('keeps the trial clock when the plan being tried is picked again', function () {
        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', ['plan' => 'essential']);
        $endsAt = $this->user->fresh()->subscription->trial_ends_at;

        $this->travel(3)->days();

        $this->postJson('/api/v1/onboarding/plan', ['plan' => 'essential'])
            ->assertOk()
            ->assertJsonPath('data.trial_ends_at', $endsAt->toIso8601String())
            ->assertJsonPath('data.trial_days_left', 11);
    });

    it('drops the account to Free once the trial has run out', function () {
        Subscription::factory()->for($this->user)->trialEnded()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);

        $this->actingAs($this->user)->getJson('/api/v1/onboarding')
            ->assertJsonPath('data.subscription.plan.id', 'free')
            ->assertJsonPath('data.subscription.chosen_plan', 'essential')
            ->assertJsonPath('data.subscription.status', 'trial_ended')
            ->assertJsonPath('data.subscription.trial_days_left', null);
    });

    it('refuses a plan or billing period that is not on sale', function (array $body, string $field) {
        Plan::where('slug', 'advanced')->update(['active' => false]);

        $this->actingAs($this->user)->postJson('/api/v1/onboarding/plan', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no plan' => [[], 'plan'],
        'an unknown plan' => [['plan' => 'gold'], 'plan'],
        'a plan off sale' => [['plan' => 'advanced'], 'plan'],
        'an unknown billing period' => [['plan' => 'free', 'billing' => 'weekly'], 'billing'],
    ]);
});
