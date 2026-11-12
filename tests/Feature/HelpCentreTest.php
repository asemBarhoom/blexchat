<?php

use App\Filament\Resources\HelpArticles\Pages\CreateHelpArticle;
use App\Models\HelpArticle;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('serves active help articles in order and falls back to English', function (): void {
    HelpArticle::factory()->create(['category' => 'account', 'sort' => 2, 'title' => ['en' => 'Second'], 'body' => ['en' => 'Answer 2']]);
    HelpArticle::factory()->create(['category' => 'account', 'sort' => 1, 'title' => ['en' => 'First', 'ar' => 'الأول'], 'body' => ['en' => 'Answer 1', 'ar' => 'الإجابة'], 'active' => true]);
    HelpArticle::factory()->create(['active' => false, 'title' => ['en' => 'Hidden'], 'body' => ['en' => 'Hidden']]);

    $this->getJson('/api/v1/help?lang=ar')->assertOk()
        ->assertJsonPath('data.articles.0.question', 'الأول')
        ->assertJsonPath('data.articles.0.answer', 'الإجابة')
        ->assertJsonPath('data.articles.1.question', 'Second')
        ->assertJsonMissing(['question' => 'Hidden']);
});

it('serves admin-managed support settings', function (): void {
    Setting::put(Setting::HELP_EMAIL, 'support@example.test');
    Setting::put(Setting::HELP_RESPONSE_TIME, 6);

    $this->getJson('/api/v1/help')->assertJsonPath('data.support', ['email' => 'support@example.test', 'response_time_hours' => 6]);
});

it('allows admins to create help articles and blocks non-admins', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(CreateHelpArticle::class)
        ->fillForm(['title' => ['en' => 'How it works', 'ar' => 'كيف يعمل'], 'body' => ['en' => 'Details', 'ar' => 'التفاصيل'], 'category' => 'general', 'sort' => 1, 'active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(HelpArticle::where('title->en', 'How it works')->exists())->toBeTrue();

    $this->actingAs(User::factory()->create()->forceFill(['is_admin' => false]))->get('/admin/help-articles')->assertForbidden();
});
