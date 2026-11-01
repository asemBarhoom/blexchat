<?php

use App\Models\ComparisonPoint;
use App\Models\Creator;
use App\Models\DemoReplyRule;
use App\Models\FaqItem;
use App\Models\HomeBlock;
use App\Models\HomeStat;
use App\Models\UseCase;
use Database\Seeders\HomeContentSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

/**
 * Image URLs are absolute and host-dependent; the fixture stores them relative.
 *
 * @param  array<array-key, mixed>  $data
 * @return array<array-key, mixed>
 */
function withRelativeUrls(array $data): array
{
    array_walk_recursive($data, function (mixed &$value): void {
        if (is_string($value)) {
            $value = str_replace(url('/').'/', '', $value);
        }
    });

    return $data;
}

it('serves exactly the homepage the frontend was built against', function () {
    $this->seed(HomeContentSeeder::class);

    $expected = json_decode(file_get_contents(base_path('tests/Fixtures/home_en.json')), true);

    $response = $this->getJson('/api/v1/home')
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonPath('meta.fallback', false)
        ->assertJsonPath('meta.available_locales', ['en', 'ar']);

    expect(withRelativeUrls($response->json('data')))->toEqual($expected);
});

it('has every homepage text in Arabic', function () {
    $this->seed(HomeContentSeeder::class);

    foreach (HomeBlock::all() as $block) {
        $texts = $block->getTranslations('texts');
        expect(array_keys(Arr::dot($texts['ar'])))->toBe(array_keys(Arr::dot($texts['en'])), "Arabic texts of {$block->key}");
    }

    foreach ([Creator::class, UseCase::class, HomeStat::class, FaqItem::class, DemoReplyRule::class, ComparisonPoint::class] as $model) {
        $model::all()->each(function (Model $item): void {
            foreach ($item->getTranslatableAttributes() as $attribute) {
                expect($item->hasTranslation($attribute, 'ar'))->toBe($item->hasTranslation($attribute, 'en'), $item::class." #{$item->id} {$attribute}");
            }
        });
    }

    $this->getJson('/api/v1/home?lang=ar')
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('meta.fallback', false)
        ->assertJsonPath('data.sections.0.subtitle', 'بلكس تشات يحوّل التعليقات والرسائل إلى عملاء.')
        ->assertJsonPath('data.sections.0.story.relief.stats.2.to', 3)
        ->assertJsonPath('data.sections.3.slides.0.cta.label', 'شاهد كيف');
});

it('serves translated text and falls back to English for the rest', function () {
    $this->seed(HomeContentSeeder::class);

    $hero = HomeBlock::where('key', 'hero')->firstOrFail();
    $hero->setTranslation('texts', 'ar', ['subtitle' => 'بلكس تشات يحوّل التعليقات والرسائل إلى عملاء.', 'story' => ['pressure' => ['chips' => [1 => ['text' => 'توصلون للرياض؟']]]]])->save();
    FaqItem::orderBy('sort')->firstOrFail()->setTranslation('question', 'ar', 'كم التكلفة؟')->forgetTranslation('answer', 'ar')->save();

    $this->getJson('/api/v1/home?lang=ar')
        ->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('meta.locale', 'ar')
        ->assertJsonPath('meta.fallback', true)
        ->assertJsonPath('data.sections.0.subtitle', 'بلكس تشات يحوّل التعليقات والرسائل إلى عملاء.')
        ->assertJsonPath('data.sections.0.description', 'Smart automations that sell and reply for you, 24/7.')
        ->assertJsonPath('data.sections.0.story.pressure.chips.0.text', 'How much is this? 😍')
        ->assertJsonPath('data.sections.0.story.pressure.chips.1.text', 'توصلون للرياض؟')
        ->assertJsonPath('data.sections.0.story.pressure.chips.1.id', 'c2')
        ->assertJsonPath('data.sections.11.items.0.question', 'كم التكلفة؟')
        ->assertJsonPath('data.sections.11.items.0.answer', 'There’s a free plan to start. Paid plans scale with your audience — see {link}.');
});

it('follows the admin order and hides inactive list items', function () {
    $this->seed(HomeContentSeeder::class);

    $first = FaqItem::orderBy('sort')->firstOrFail();
    $first->update(['sort' => 99]);
    FaqItem::orderBy('sort')->firstOrFail()->update(['active' => false]);

    $this->getJson('/api/v1/home')
        ->assertJsonCount(5, 'data.sections.11.items')
        ->assertJsonPath('data.sections.11.items.0.question', 'Does it work in Arabic?')
        ->assertJsonPath('data.sections.11.items.4.question', $first->getTranslation('question', 'en'));
});

it('sends Arabic and slashes unescaped', function () {
    $this->seed(HomeContentSeeder::class);

    $response = $this->get('/api/v1/home?lang=ar')->assertHeader('Content-Type', 'application/json; charset=utf-8');
    $raw = $response->getContent();

    expect($raw)->toContain('بلكس تشات')
        ->not->toContain('\u0')
        ->not->toContain('\/');
});

it('gives every link the frontend format and never a raw url', function () {
    $this->seed(HomeContentSeeder::class);

    $response = $this->getJson('/api/v1/home');

    expect($response->getContent())->not->toContain('"url":')->not->toContain('"target":');

    $response
        ->assertJsonPath('data.sections.3.slides.1.cta', ['label' => 'See how', 'section' => 'see-it-in-action', 'tab' => 'faq'])
        ->assertJsonPath('data.sections.3.slides.2.cta', ['label' => 'See how', 'section' => 'try-it'])
        ->assertJsonPath('data.sections.3.slides.3.cta', ['label' => 'See how', 'to' => '/signup'])
        ->assertJsonPath('data.sections.11.items.0.answer', 'There’s a free plan to start. Paid plans scale with your audience — see {link}.')
        ->assertJsonPath('data.sections.11.items.0.link', ['label' => 'Pricing', 'to' => '/pricing']);
});

it('serves no navbar or footer, which the frontend owns', function () {
    $this->seed(HomeContentSeeder::class);

    expect($this->getJson('/api/v1/home')->json('data'))->toHaveKeys(['sections', 'demo_replies'])->not->toHaveKeys(['navigation', 'footer']);
});

it('gives hero channels and partners their brand mark', function () {
    $this->seed(HomeContentSeeder::class);

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.sections.0.channels.0', ['id' => 'instagram', 'label' => 'Instagram'])
        ->assertJsonPath('data.sections.0.partners.0', ['id' => 'meta', 'label' => 'Meta Business Partner']);
});

it('returns a 404 before the homepage content is seeded', function () {
    $this->getJson('/api/v1/home')->assertNotFound();
});
