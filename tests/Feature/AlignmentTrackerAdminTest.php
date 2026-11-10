<?php

use App\Filament\Resources\AlignmentItemResource\Pages\ManageAlignmentItems;
use App\Models\AlignmentItem;
use App\Models\User;
use Database\Seeders\AlignmentItemSeeder;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('seeds every gap once, with a valid owner, priority and status, and keeps a status changed in the panel', function () {
    $this->seed(AlignmentItemSeeder::class);

    $items = AlignmentItem::all();
    expect($items->where('owner', 'frontend'))->toHaveCount(26)
        ->and($items->pluck('owner')->unique()->diff(array_keys(AlignmentItem::owners())))->toBeEmpty()
        ->and($items->pluck('priority')->unique()->diff(array_keys(AlignmentItem::priorities())))->toBeEmpty()
        ->and($items->pluck('status')->unique()->diff(array_keys(AlignmentItem::statuses())))->toBeEmpty()
        ->and(AlignmentItem::firstWhere('code', 'FE-07')->figma_url)->toBe(AlignmentItem::FIGMA_FILE.'?node-id=49-10433');

    AlignmentItem::where('code', 'FE-07')->update(['status' => 'done']);
    $this->seed(AlignmentItemSeeder::class);

    expect(AlignmentItem::count())->toBe($items->count())
        ->and(AlignmentItem::firstWhere('code', 'FE-07')->status)->toBe('done');
});

it('syncs the deployed completion statuses without overwriting a manual panel change', function () {
    AlignmentItem::factory()->create(['code' => 'BE-06', 'status' => 'open']);
    AlignmentItem::factory()->create(['code' => 'FE-07', 'status' => 'done']);

    $this->seed(AlignmentItemSeeder::class);

    expect(AlignmentItem::where('code', 'BE-06')->value('status'))->toBe('done')
        ->and(AlignmentItem::where('code', 'FE-07')->value('status'))->toBe('done');
});

it('lists the items, filters them by owner, and changes a status from the table', function () {
    $frontend = AlignmentItem::factory()->create(['code' => 'FE-07']);
    $backend = AlignmentItem::factory()->backend()->create();

    Livewire::test(ManageAlignmentItems::class)
        ->assertCanSeeTableRecords([$frontend, $backend])
        ->filterTable('owner', 'backend')
        ->assertCanSeeTableRecords([$backend])
        ->assertCanNotSeeTableRecords([$frontend])
        ->resetTableFilters()
        ->call('updateTableColumnState', 'status', (string) $frontend->getKey(), 'partial');

    expect($frontend->fresh()->status)->toBe('partial');
});

it('adds an item and refuses a code that is already taken', function () {
    AlignmentItem::factory()->create(['code' => 'BE-06']);

    Livewire::test(ManageAlignmentItems::class)
        ->callAction(CreateAction::class, ['code' => 'BE-06', 'title' => 'Reminder email', 'owner' => 'backend', 'priority' => 'p1', 'status' => 'open'])
        ->assertHasFormErrors(['code' => 'unique']);

    Livewire::test(ManageAlignmentItems::class)
        ->callAction(CreateAction::class, ['code' => 'BE-19', 'title' => 'Reminder email', 'owner' => 'backend', 'priority' => 'p1', 'status' => 'open', 'figma_url' => AlignmentItem::figmaNode('55:6087')])
        ->assertHasNoFormErrors();

    expect(AlignmentItem::firstWhere('code', 'BE-19')->owner)->toBe('backend');
});
