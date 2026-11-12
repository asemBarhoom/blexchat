<?php

use App\Filament\Resources\PlanResource\Pages\ManagePlans;
use App\Filament\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use App\Filament\Resources\SurveyAnswerResource\Pages\ManageSurveyAnswers;
use App\Filament\Resources\SurveyQuestionResource\Pages\CreateSurveyQuestion;
use App\Filament\Resources\SurveyQuestionResource\Pages\EditSurveyQuestion;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\User;
use Database\Seeders\OnboardingSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OnboardingSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a plan in both languages', function () {
    Livewire::test(ManagePlans::class)
        ->callAction(CreateAction::class, [
            'slug' => 'starter',
            'name' => ['en' => 'Starter', 'ar' => 'البداية'],
            'monthly_price' => 9,
            'annual_price' => 7,
            'contacts_limit' => 100,
            'teammates_limit' => 1,
            'trial_days' => 10,
            'features' => ['en' => ['2 channels'], 'ar' => ['قناتان']],
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $plan = Plan::where('slug', 'starter')->firstOrFail();
    expect($plan->sort)->toBe(5)
        ->and($plan->getTranslations('features'))->toBe(['en' => ['2 channels'], 'ar' => ['قناتان']]);

    $this->getJson('/api/v1/plans?lang=ar')->assertJsonPath('data.5.name', 'البداية')->assertJsonPath('data.5.trial_days', 10);

    Livewire::test(ManagePlans::class)
        ->callAction(TestAction::make('edit')->table($plan), ['monthly_price' => 12])
        ->assertHasNoFormErrors();
    expect($plan->fresh()->monthly_price)->toBe(12);

    Livewire::test(ManagePlans::class)->callAction(TestAction::make('delete')->table($plan));
    expect(Plan::find($plan->id))->toBeNull();
});

it('keeps Free and plans with subscribers from being deleted', function () {
    $essential = Plan::where('slug', 'essential')->firstOrFail();
    Subscription::factory()->create(['plan_id' => $essential->id]);

    Livewire::test(ManagePlans::class)
        ->assertActionHidden(TestAction::make('delete')->table(Plan::free()))
        ->assertActionHidden(TestAction::make('delete')->table($essential))
        ->assertActionVisible(TestAction::make('delete')->table(Plan::where('slug', 'pro')->firstOrFail()));
});

it('creates a survey question with its options and shows it to owners', function () {
    Livewire::test(CreateSurveyQuestion::class)
        ->fillForm([
            'key' => 'team',
            'kind' => 'radio',
            'question' => ['en' => 'How big is your team?', 'ar' => 'ما حجم فريقك؟'],
            'active' => true,
            'options' => [
                ['key' => 'solo', 'label' => ['en' => 'Just me', 'ar' => 'أنا فقط']],
                ['key' => 'small', 'label' => ['en' => '2–10', 'ar' => '2–10']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = SurveyQuestion::where('key', 'team')->firstOrFail();
    expect($question->options->pluck('key')->all())->toBe(['solo', 'small']);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/onboarding/survey?lang=ar')
        ->assertJsonPath('data.4.question', 'ما حجم فريقك؟')
        ->assertJsonPath('data.4.options.0.label', 'أنا فقط');
});

it('gives each option a simple-icons logo, a cropped wordmark or an uploaded one', function () {
    Storage::fake('public');

    Livewire::test(CreateSurveyQuestion::class)
        ->fillForm([
            'key' => 'crm',
            'kind' => 'grid',
            'question' => ['en' => 'Which CRM do you use?'],
            'active' => true,
            'options' => [
                ['key' => 'hubspot', 'label' => ['en' => 'HubSpot'], 'icon' => 'hubspot', 'icon_box' => ['viewBox' => '', 'width' => '', 'height' => '']],
                ['key' => 'zoho', 'label' => ['en' => 'Zoho'], 'icon' => 'zoho', 'icon_box' => ['viewBox' => '0 9.5 24 5', 'width' => '60', 'height' => '12']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $options = SurveyQuestion::where('key', 'crm')->firstOrFail()->options->keyBy('key');
    expect($options['hubspot']->icon_box)->toBeNull()
        ->and($options['zoho']->icon_box)->toBe(['viewBox' => '0 9.5 24 5', 'width' => 60, 'height' => 12]);

    $test = Livewire::test(EditSurveyQuestion::class, ['record' => SurveyQuestion::where('key', 'crm')->firstOrFail()->getRouteKey()]);
    $secondItem = array_keys($test->get('data.options'))[1];
    $test->fillForm(["options.{$secondItem}.icon_image" => [UploadedFile::fake()->create('zoho.png', 100, 'image/png')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $zoho = $options['zoho']->fresh();
    Storage::disk('public')->assertExists($zoho->icon_image);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/onboarding/survey')
        ->assertJsonPath('data.4.options.0.icon', 'hubspot')
        ->assertJsonPath('data.4.options.0.icon_url', null)
        ->assertJsonPath('data.4.options.1.icon_box.width', 60)
        ->assertJsonPath('data.4.options.1.icon_url', Storage::disk('public')->url($zoho->icon_image));
});

it('refuses an icon that is not a simple-icons slug, and a half-filled crop', function (array $option, string $field) {
    $question = SurveyQuestion::where('key', 'heard')->firstOrFail();

    $test = Livewire::test(EditSurveyQuestion::class, ['record' => $question->getRouteKey()]);
    $firstItem = array_key_first($test->get('data.options'));

    $test->fillForm(['options' => [$firstItem => ['key' => 'instagram', 'label' => ['en' => 'Instagram'], ...$option]]])
        ->call('save')
        ->assertHasFormErrors(["options.{$firstItem}.{$field}"]);
})->with([
    'an icon name with capitals' => [['icon' => 'Instagram'], 'icon'],
    'an icon URL' => [['icon' => 'https://cdn.simpleicons.org/instagram'], 'icon'],
    'a viewBox with three numbers' => [['icon_box' => ['viewBox' => '0 0 24', 'width' => '20', 'height' => '20']], 'icon_box.viewBox'],
    'a viewBox without a size' => [['icon_box' => ['viewBox' => '0 0 24 24', 'width' => '', 'height' => '']], 'icon_box.width'],
]);

it('refuses an "other" option that is not one of the question’s options', function () {
    $question = SurveyQuestion::where('key', 'heard')->firstOrFail();

    Livewire::test(EditSurveyQuestion::class, ['record' => $question->getRouteKey()])
        ->fillForm(['other_option' => 'podcast'])
        ->call('save')
        ->assertHasFormErrors(['other_option']);
});

it('creates, edits and deletes a survey answer, one per question and owner', function () {
    $owner = User::factory()->create();
    $question = SurveyQuestion::where('key', 'money')->firstOrFail();

    Livewire::test(ManageSurveyAnswers::class)
        ->callAction(CreateAction::class, ['user_id' => $owner->id, 'survey_question_id' => $question->id, 'options' => ['products']])
        ->assertHasNoFormErrors();

    $answer = SurveyAnswer::where('user_id', $owner->id)->firstOrFail();
    expect($answer->options)->toBe(['products']);

    Livewire::test(ManageSurveyAnswers::class)
        ->callAction(CreateAction::class, ['user_id' => $owner->id, 'survey_question_id' => $question->id, 'options' => ['deals']])
        ->assertHasFormErrors(['survey_question_id' => 'unique']);

    Livewire::test(ManageSurveyAnswers::class)
        ->callAction(TestAction::make('edit')->table($answer), ['options' => ['products', 'courses']])
        ->assertHasNoFormErrors();
    expect($answer->fresh()->options)->toBe(['products', 'courses']);

    Livewire::test(ManageSurveyAnswers::class)->callAction(TestAction::make('delete')->table($answer));
    expect(SurveyAnswer::find($answer->id))->toBeNull();
});

it('creates, edits and deletes a subscription, one per owner', function () {
    $owner = User::factory()->create();
    $essential = Plan::where('slug', 'essential')->firstOrFail();

    Livewire::test(ManageSubscriptions::class)
        ->callAction(CreateAction::class, [
            'user_id' => $owner->id,
            'plan_id' => $essential->id,
            'billing_period' => 'annual',
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(10)->toDateTimeString(),
        ])
        ->assertHasNoFormErrors();

    $subscription = $owner->subscription()->firstOrFail();
    $this->actingAs($owner)->getJson('/api/v1/onboarding')
        ->assertJsonPath('data.subscription.status', 'trialing')
        ->assertJsonPath('data.subscription.billing_period', 'annual');

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(ManageSubscriptions::class)
        ->callAction(CreateAction::class, ['user_id' => $owner->id, 'plan_id' => Plan::free()->id, 'billing_period' => 'monthly', 'status' => 'active'])
        ->assertHasFormErrors(['user_id' => 'unique']);

    Livewire::test(ManageSubscriptions::class)
        ->callAction(TestAction::make('edit')->table($subscription), ['plan_id' => Plan::free()->id, 'status' => 'active'])
        ->assertHasNoFormErrors();
    expect($subscription->fresh()->plan->slug)->toBe('free');

    Livewire::test(ManageSubscriptions::class)->callAction(TestAction::make('delete')->table($subscription));
    expect($owner->subscription()->exists())->toBeFalse();
});

it('shows where an owner is in onboarding on their admin page', function () {
    $owner = User::factory()->create();
    Subscription::factory()->for($owner)->trialing()->create(['plan_id' => Plan::where('slug', 'essential')->value('id')]);

    $this->get("/admin/users/{$owner->id}")
        ->assertOk()
        ->assertSee('ربط قناة')
        ->assertSee('أساسية');
});
