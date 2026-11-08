<?php

use App\Filament\Pages\ManagePricingPage;
use App\Filament\Resources\PricingCompareGroupResource\Pages\ManagePricingCompareGroups;
use App\Filament\Resources\PricingCompareRowResource\Pages\CreatePricingCompareRow;
use App\Filament\Resources\PricingCompareRowResource\Pages\EditPricingCompareRow;
use App\Filament\Resources\PricingCompareRowResource\Pages\ListPricingCompareRows;
use App\Filament\Resources\PricingFaqItemResource\Pages\ManagePricingFaqItems;
use App\Models\Plan;
use App\Models\PricingCompareGroup;
use App\Models\PricingCompareRow;
use App\Models\PricingFaqItem;
use App\Models\PricingPage;
use App\Models\User;
use Database\Seeders\OnboardingSeeder;
use Database\Seeders\PricingPageSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([OnboardingSeeder::class, PricingPageSeeder::class]);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a comparison row with a cell per plan and shows it on the page', function () {
    $team = PricingCompareGroup::where('key', 'team')->firstOrFail();
    $plans = Plan::query()->pluck('id', 'slug');

    Livewire::test(CreatePricingCompareRow::class)
        ->assertFormSet(fn (array $state): bool => count($state['cells']) === 5)
        ->fillForm([
            'pricing_compare_group_id' => $team->id,
            'key' => 'audit-log',
            'label' => ['en' => 'Audit log', 'ar' => 'سجل التغييرات'],
            'active' => true,
            'cells' => [
                ['plan_id' => $plans['free'], 'included' => false],
                ['plan_id' => $plans['business'], 'included' => true],
                ['plan_id' => $plans['advanced'], 'included' => false, 'text' => ['en' => '{contacts} entries', 'ar' => '{contacts} سجل']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = PricingCompareRow::where('key', 'audit-log')->firstOrFail();
    expect($row->group->is($team))->toBeTrue()->and($row->cells)->toHaveCount(3);

    $rows = collect($this->getJson('/api/v1/pricing?lang=ar')->json('data.compare.2.rows'))->keyBy('id');
    expect($rows['audit-log'])->toBe(['id' => 'audit-log', 'label' => 'سجل التغييرات', 'cells' => [
        'free' => false, 'essential' => false, 'pro' => false, 'business' => true, 'advanced' => '25,000 سجل',
    ]]);
});

it('refuses two cells for the same plan', function () {
    $free = Plan::free();

    Livewire::test(CreatePricingCompareRow::class)
        ->fillForm([
            'pricing_compare_group_id' => PricingCompareGroup::firstOrFail()->id,
            'key' => 'twice',
            'label' => ['en' => 'Twice'],
            'cells' => [['plan_id' => $free->id], ['plan_id' => $free->id]],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(PricingCompareRow::where('key', 'twice')->exists())->toBeFalse();
});

it('edits a row\'s cells and deletes the row', function () {
    $row = PricingCompareRow::where('key', 'onboarding-call')->firstOrFail();
    $business = Plan::where('slug', 'business')->firstOrFail();

    $undoRepeaterFake = Repeater::fake();
    $component = Livewire::test(EditPricingCompareRow::class, ['record' => $row->getRouteKey()]);
    $cells = $component->get('data.cells');
    $businessItem = collect($cells)->search(fn (array $cell): bool => (int) $cell['plan_id'] === $business->id);
    $cells[$businessItem]['included'] = true;

    $component->fillForm(['cells' => $cells])->call('save')->assertHasNoFormErrors();
    $undoRepeaterFake();

    expect($row->cells()->where('plan_id', $business->id)->firstOrFail()->included)->toBeTrue();

    Livewire::test(ListPricingCompareRows::class)->callAction(TestAction::make('delete')->table($row));
    $this->assertModelMissing($row);
});

it('creates, edits and deletes a comparison group', function () {
    Livewire::test(ManagePricingCompareGroups::class)
        ->callAction(CreateAction::class, ['key' => 'security', 'title' => ['en' => 'Security', 'ar' => 'الأمان'], 'active' => true])
        ->assertHasNoFormErrors();

    $group = PricingCompareGroup::where('key', 'security')->firstOrFail();
    expect($group->sort)->toBe(4);

    Livewire::test(ManagePricingCompareGroups::class)
        ->callAction(TestAction::make('edit')->table($group), ['key' => 'audience'])
        ->assertHasFormErrors(['key' => 'unique']);

    Livewire::test(ManagePricingCompareGroups::class)->callAction(TestAction::make('delete')->table($group));
    $this->assertModelMissing($group);
});

it('creates and deletes a pricing question', function () {
    Livewire::test(ManagePricingFaqItems::class)
        ->callAction(CreateAction::class, [
            'question' => ['en' => 'Do you offer refunds?', 'ar' => 'هل تستردون المبلغ؟'],
            'answer' => ['en' => 'Within {trial_days} days.', 'ar' => 'خلال {trial_days} يوماً.'],
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $this->getJson('/api/v1/pricing')->assertJsonPath('data.faq.6.answer', 'Within 14 days.');

    $item = PricingFaqItem::query()->latest('id')->firstOrFail();
    Livewire::test(ManagePricingFaqItems::class)->callAction(TestAction::make('delete')->table($item));
    $this->assertModelMissing($item);
});

it('saves the page copy, a cleared Arabic text falling back to English', function () {
    Livewire::test(ManagePricingPage::class)
        ->assertFormSet(['texts.ar.hero.title' => 'ابدأ مجانًا. وادفع حين يكبر جمهورك.'])
        ->fillForm([
            'texts.en.hero.title' => 'Free to start.',
            'texts.ar.hero.title' => '',
            'agency_url' => '/agencies',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page = PricingPage::current();
    expect($page->getTranslations('texts')['ar']['hero'])->not->toHaveKey('title')
        ->and($page->agency_url)->toBe('/agencies');

    $this->getJson('/api/v1/pricing?lang=ar')
        ->assertJsonPath('data.copy.hero.title', 'Free to start.')
        ->assertJsonPath('data.copy.agency.link_url', '/agencies')
        ->assertJsonPath('meta.fallback', true);
});

it('requires the English copy', function () {
    Livewire::test(ManagePricingPage::class)
        ->fillForm(['texts.en.hero.title' => ''])
        ->call('save')
        ->assertHasFormErrors(['texts.en.hero.title' => 'required']);
});
