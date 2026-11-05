<?php

use App\Filament\Resources\LegalPageResource\Pages\EditLegalPage;
use App\Filament\Resources\LegalPageResource\Pages\ListLegalPages;
use App\Models\LegalPage;
use App\Models\User;
use Database\Seeders\LegalPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('GET /api/v1/legal/{slug}', function () {
    it('serves the page in the requested language', function () {
        LegalPage::factory()->create();

        $this->getJson('/api/v1/legal/privacy?lang=ar')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertExactJson([
                'data' => ['slug' => 'privacy', 'title' => 'سياسة الخصوصية', 'body' => "## ما الذي نجمعه\n\nبريدك الإلكتروني.", 'format' => 'markdown', 'effective_on' => '2026-11-01'],
                'meta' => ['locale' => 'ar', 'fallback' => false, 'available_locales' => ['en', 'ar']],
            ]);
    });

    it('falls back to English for a page not yet translated', function () {
        LegalPage::factory()->terms()->create(['body' => ['en' => 'Keep your account safe.']]);

        $this->getJson('/api/v1/legal/terms?lang=ar')
            ->assertOk()
            ->assertJsonPath('data.title', 'شروط الخدمة')
            ->assertJsonPath('data.body', 'Keep your account safe.')
            ->assertJsonPath('meta.fallback', true);
    });

    it('answers 404 for any other page, and before the pages are seeded', function () {
        $this->getJson('/api/v1/legal/privacy')->assertNotFound();

        LegalPage::factory()->create(['slug' => 'cookies']);
        $this->getJson('/api/v1/legal/cookies')->assertNotFound();
    });
});

it('seeds both pages in both languages and never overwrites an edit', function () {
    $this->seed(LegalPageSeeder::class);
    LegalPage::forSlug('terms')->firstOrFail()->setTranslation('title', 'en', 'Edited terms')->save();
    $this->seed(LegalPageSeeder::class);

    expect(LegalPage::pluck('slug')->all())->toBe(LegalPage::SLUGS)
        ->and(LegalPage::forSlug('terms')->firstOrFail()->getTranslation('title', 'en'))->toBe('Edited terms')
        ->and(LegalPage::all()->every(fn (LegalPage $page): bool => ! $page->isMissingTextsIn('ar')))->toBeTrue();
});

it('lets an admin edit a page’s text and date, but not add one', function () {
    $page = LegalPage::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListLegalPages::class)->assertCanSeeTableRecords([$page])->assertActionDoesNotExist('create');

    Livewire::test(EditLegalPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['body.ar' => "## بياناتك\n\nلا نبيعها.", 'effective_on' => '2026-12-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->getJson('/api/v1/legal/privacy?lang=ar')
        ->assertJsonPath('data.body', "## بياناتك\n\nلا نبيعها.")
        ->assertJsonPath('data.effective_on', '2026-12-01');
});
