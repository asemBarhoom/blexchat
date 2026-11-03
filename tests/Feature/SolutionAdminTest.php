<?php

use App\Filament\Resources\SolutionResource\Pages\CreateSolution;
use App\Filament\Resources\SolutionResource\Pages\EditSolution;
use App\Filament\Resources\SolutionResource\Pages\ListSolutions;
use App\Filament\Resources\UseCaseResource\Pages\ManageUseCases;
use App\Models\Solution;
use App\Models\UseCase;
use App\Models\User;
use Database\Seeders\HomeContentSeeder;
use Database\Seeders\SolutionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([HomeContentSeeder::class, SolutionSeeder::class]);
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @return list<array<string, mixed>>
 */
function solutionAutomationsForm(): array
{
    return array_map(fn (int $i): array => [
        'title' => ['en' => "Automation {$i}", 'ar' => "أتمتة {$i}"],
        'line' => ['en' => 'A line.'],
        'trigger_source' => 'dm',
        'trigger_words' => [],
        'customer' => 'كم الاشتراك؟',
        'reply' => 'هلا! الباقات هنا:',
        'button' => 'احجز حصتك',
        'link' => 'qimmagym.com/trial',
    ], [1, 2, 3]);
}

it('creates a page without a card, with its automations, steps and questions', function () {
    Livewire::test(CreateSolution::class)
        ->fillForm([
            'slug' => 'gyms',
            'name' => ['en' => 'Gyms', 'ar' => 'النوادي الرياضية'],
            'shape' => 'pill',
            'tone' => 'brand-ink',
            'headline' => ['en' => 'Members sign up from a DM.', 'ar' => 'المشتركون يسجّلون من رسالة.'],
            'supporting' => ['en' => 'Supporting.'],
            'closing' => ['en' => 'Closing.'],
            'sample_handle' => 'qimmagym',
            'avatar_color' => '#0f7a5a',
            'avatar_text_color' => '#ffffff',
            'active' => true,
            'automations' => solutionAutomationsForm(),
            'steps' => [['text' => ['en' => 'Connect.', 'ar' => 'اربط.']], ['text' => ['en' => 'Go live.']]],
            'faqItems' => [['question' => ['en' => 'Is it hard?'], 'answer' => ['en' => 'No.']]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $solution = Solution::forSlug('gyms')->firstOrFail();
    expect($solution->sort)->toBe(9)
        ->and($solution->automations()->pluck('sort')->all())->toBe([1, 2, 3]);

    $this->getJson('/api/v1/solutions/gyms?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.name', 'النوادي الرياضية')
        ->assertJsonPath('data.tone', 'brand-ink')
        ->assertJsonPath('data.automations.2.title', 'أتمتة 3')
        ->assertJsonPath('data.steps', ['اربط.', 'Go live.'])
        ->assertJsonPath('data.faq.0.a', 'No.');
});

it('refuses fewer than three automations, a long button and a slug a card already has', function () {
    $automations = solutionAutomationsForm();
    $automations[0]['button'] = str_repeat('ز', 21);

    Livewire::test(CreateSolution::class)
        ->fillForm(['slug' => 'clinics', 'automations' => array_slice($automations, 0, 2)])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'not_in', 'automations' => 'min']);

    Livewire::test(CreateSolution::class)
        ->fillForm(['automations' => $automations])
        ->call('create')
        ->assertHasFormErrors(['automations.0.button' => 'max']);
});

it('edits a page and its sections', function () {
    $solution = Solution::forSlug('clinics')->firstOrFail();

    Livewire::test(EditSolution::class, ['record' => $solution->getRouteKey()])
        ->assertFormFieldHidden('slug')
        ->fillForm(['headline' => ['en' => 'New headline', 'ar' => 'عنوان جديد']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($solution->fresh()->getTranslation('headline', 'ar'))->toBe('عنوان جديد')
        ->and($solution->automations()->count())->toBe(3);
});

it('links a standalone page to a card and drops its own identity', function () {
    $solution = Solution::forSlug('education')->firstOrFail();
    $useCase = UseCase::factory()->create(['slug' => 'schools', 'name' => ['en' => 'Schools']]);

    Livewire::test(EditSolution::class, ['record' => $solution->getRouteKey()])
        ->fillForm(['use_case_id' => $useCase->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($solution->fresh())->slug->toBeNull()->tone->toBeNull()->getRawOriginal('name')->toBeNull();
    $this->getJson('/api/v1/solutions/schools')->assertOk()->assertJsonPath('data.name', 'Schools');
});

it('refuses linking two pages to one card', function () {
    $solution = Solution::forSlug('education')->firstOrFail();

    Livewire::test(EditSolution::class, ['record' => $solution->getRouteKey()])
        ->fillForm(['use_case_id' => UseCase::where('slug', 'clinics')->value('id')])
        ->call('save')
        ->assertHasFormErrors(['use_case_id' => 'unique']);
});

it('hides and deletes a page with its sections', function () {
    $solution = Solution::forSlug('support')->firstOrFail();

    Livewire::test(ListSolutions::class)->call('updateTableColumnState', 'active', (string) $solution->getKey(), false);
    $this->getJson('/api/v1/solutions/support')->assertNotFound();

    Livewire::test(ListSolutions::class)->callAction(TestAction::make('delete')->table($solution));

    $this->assertModelMissing($solution);
    $this->assertDatabaseMissing('solution_automations', ['solution_id' => $solution->id]);
});

it('keeps the page when its card is deleted', function () {
    $useCase = UseCase::where('slug', 'agencies')->firstOrFail();

    Livewire::test(ManageUseCases::class)->callAction(TestAction::make('delete')->table($useCase));

    $this->assertModelMissing($useCase);
    $this->getJson('/api/v1/solutions/agencies?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.name', 'الوكالات')
        ->assertJsonPath('data.shape', 'pill');
});

it('renders the edit page of every seeded page', function () {
    Solution::all()->each(fn (Solution $solution) => $this->get("/admin/solutions/{$solution->id}/edit")->assertOk());
});
