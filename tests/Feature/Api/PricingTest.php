<?php

use App\Models\Plan;
use App\Models\PricingCompareCell;
use App\Models\PricingCompareGroup;
use App\Models\PricingCompareRow;
use App\Models\PricingFaqItem;
use App\Models\PricingPage;
use Database\Seeders\OnboardingSeeder;
use Database\Seeders\PricingPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, PricingPageSeeder::class]);
});

it('serves the pricing page in Arabic with figures read from the plans', function () {
    $response = $this->getJson('/api/v1/pricing?lang=ar')
        ->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('meta.locale', 'ar')
        ->assertJsonPath('meta.fallback', false)
        ->assertJsonPath('data.max_saving', 30)
        ->assertJsonPath('data.copy.hero.save', 'وفّر حتى 30%')
        ->assertJsonPath('data.copy.card.try_free', 'جرّب مجانًا لمدة {days} يوم')
        ->assertJsonPath('data.copy.agency.link_url', '/solutions/agencies')
        ->assertJsonPath('data.compare.0.id', 'audience')
        ->assertJsonPath('data.compare.0.title', 'الجمهور')
        ->assertJsonPath('data.compare.0.rows.0.cells.free', 'حتى 25')
        ->assertJsonPath('data.compare.0.rows.0.cells.advanced', 'حتى 25,000')
        ->assertJsonPath('data.faq.0.answer', 'نعم. بدون بطاقة وبدون حد زمني. تحصل على قناة واحدة وما يصل إلى 25 جهة اتصال، ويمكنك البقاء عليها ما شئت.')
        ->assertJsonPath('data.faq.5.answer', 'باقة أساسية تأتي بتجربة مجانية مدتها 14 يوم. لن تدفع شيئًا حتى تنتهي التجربة، وسنذكّرك قبل انتهائها.');

    $automation = collect($response->json('data.compare.1.rows'))->keyBy('id');
    expect($automation['follow-ups']['cells'])->toBe(['free' => false, 'essential' => false, 'pro' => true, 'business' => true, 'advanced' => true])
        ->and($automation['ai']['cells'])->toBe(['free' => false, 'essential' => false, 'pro' => true, 'business' => true, 'advanced' => true])
        ->and($automation['no-branding']['cells'])->toBe(['free' => false, 'essential' => true, 'pro' => true, 'business' => true, 'advanced' => true]);
});

it('serves English by default', function () {
    $this->getJson('/api/v1/pricing')
        ->assertOk()
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonPath('data.copy.hero.title', 'Start free. Pay when your audience grows.')
        ->assertJsonPath('data.compare.3.rows.0.cells.business', 'Priority');
});

it('reads table cells from the plan when a row takes them from a plan field', function () {
    Plan::where('slug', 'essential')->update(['has_ai' => true]);

    $rows = collect($this->getJson('/api/v1/pricing')->json('data.compare.1.rows'))->keyBy('id');

    expect($rows['ai']['cells']['essential'])->toBeTrue();
});

it('falls back to English text by text and says so', function () {
    $row = PricingCompareRow::where('key', 'channels')->firstOrFail();
    $row->forgetTranslation('label', 'ar')->save();

    $page = PricingPage::current();
    $texts = $page->getTranslations('texts');
    unset($texts['ar']['hero']['title']);
    $page->replaceTranslations('texts', $texts)->save();

    $this->getJson('/api/v1/pricing?lang=ar')
        ->assertJsonPath('meta.fallback', true)
        ->assertJsonPath('data.compare.0.rows.1.label', 'Channels')
        ->assertJsonPath('data.copy.hero.title', 'Start free. Pay when your audience grows.')
        ->assertJsonPath('data.copy.hero.chip', 'الأسعار');
});

it('leaves out hidden groups, rows, questions and plans, and gives a plan with no cell a dash', function () {
    PricingCompareGroup::where('key', 'support')->update(['active' => false]);
    PricingCompareRow::where('key', 'channels')->update(['active' => false]);
    PricingFaqItem::query()->oldest('sort')->firstOrFail()->update(['active' => false]);
    Plan::where('slug', 'advanced')->update(['active' => false]);
    $starter = Plan::factory()->create(['slug' => 'starter']);

    $response = $this->getJson('/api/v1/pricing')->assertOk();

    expect(collect($response->json('data.compare'))->pluck('id')->all())->toBe(['audience', 'automation', 'team'])
        ->and(collect($response->json('data.compare.0.rows'))->pluck('id')->all())->toBe(['contacts'])
        ->and($response->json('data.compare.2.rows.1.cells'))->toBe(['free' => false, 'essential' => false, 'pro' => false, 'business' => true, 'starter' => false])
        ->and($response->json('data.faq'))->toHaveCount(5)
        ->and(PricingCompareCell::where('plan_id', $starter->id)->exists())->toBeFalse();
});

it('returns not found before the page is seeded', function () {
    PricingPage::query()->delete();

    $this->getJson('/api/v1/pricing')->assertNotFound();
});

it('seeds the pricing page once however often it runs', function () {
    $this->seed(PricingPageSeeder::class);

    expect(PricingPage::count())->toBe(1)
        ->and(PricingCompareGroup::count())->toBe(4)
        ->and(PricingCompareRow::count())->toBe(12)
        ->and(PricingCompareCell::count())->toBe(50)
        ->and(PricingFaqItem::count())->toBe(6);
});
