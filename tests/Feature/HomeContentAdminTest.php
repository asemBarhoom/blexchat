<?php

use App\Filament\Pages\ManageMarketingHome;
use App\Filament\Resources\FaqItemResource\Pages\ManageFaqItems;
use App\Models\FaqItem;
use App\Models\HomeBlock;
use App\Models\User;
use Database\Seeders\HomeContentSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(HomeContentSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @return array<string, array{texts: array<string, mixed>, settings: array<string, mixed>}>
 */
function storedBlocks(): array
{
    return HomeBlock::all()->mapWithKeys(fn (HomeBlock $block): array => [
        $block->key => ['texts' => $block->getTranslations('texts'), 'settings' => $block->settings],
    ])->all();
}

it('saves the sections page without changing anything when nothing was edited', function () {
    $before = storedBlocks();

    Livewire::test(ManageMarketingHome::class)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedBlocks())->toBe($before);
});

it('stores English and Arabic texts separately and serves each language', function () {
    Livewire::test(ManageMarketingHome::class)
        ->fillForm([
            'hero.texts.en.subtitle' => 'Turn every DM into a sale.',
            'hero.texts.ar.subtitle' => 'حوّل كل رسالة إلى عملية بيع.',
            'hero.texts.ar.description' => '',
            'use-cases.texts.ar.heading' => 'مصمّم لطريقتك في البيع',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.sections.0.subtitle', 'Turn every DM into a sale.')
        ->assertJsonPath('data.sections.5.heading', 'Built for the way YOU sell')
        ->assertJsonPath('data.sections.3.slides.0.cta', ['label' => 'See how', 'section' => 'see-it-in-action', 'tab' => 'comment']);

    $this->getJson('/api/v1/home?lang=ar')
        ->assertJsonPath('data.sections.0.subtitle', 'حوّل كل رسالة إلى عملية بيع.')
        ->assertJsonPath('data.sections.0.description', 'Smart automations that sell and reply for you, 24/7.')
        ->assertJsonPath('data.sections.5.heading', 'مصمّم لطريقتك في البيع')
        ->assertJsonPath('data.sections.5.items.1.shape', 'blob')
        ->assertJsonPath('meta.fallback', true);
});

it('requires every English text but no Arabic one', function () {
    Livewire::test(ManageMarketingHome::class)
        ->fillForm(['hero.texts.en.subtitle' => '', 'hero.texts.ar.subtitle' => ''])
        ->call('save')
        ->assertHasFormErrors(['hero.texts.en.subtitle' => 'required'])
        ->assertHasNoFormErrors(['hero.texts.ar.subtitle']);
});

it('stores an uploaded section image and serves it as an absolute URL', function () {
    Storage::fake('public');

    foreach (HomeBlock::all() as $block) {
        $settings = $block->settings;
        array_walk_recursive($settings, function (mixed $value): void {
            if (is_string($value) && str_starts_with($value, 'marketing/')) {
                Storage::disk('public')->put($value, 'image');
            }
        });
    }

    Livewire::test(ManageMarketingHome::class)
        ->fillForm(['autopilot.settings.photo' => [UploadedFile::fake()->image('new.png')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = HomeBlock::where('key', 'autopilot')->firstOrFail()->settings['photo'];

    expect($path)->toStartWith('marketing/home/uploads/');
    Storage::disk('public')->assertExists($path);
    $this->getJson('/api/v1/home')->assertJsonPath('data.sections.4.photo', url('storage/'.$path));
});

it('creates, edits, reorders and deletes FAQ items in both languages', function () {
    Livewire::test(ManageFaqItems::class)
        ->callAction(CreateAction::class, [
            'question' => ['en' => 'Can I cancel anytime?', 'ar' => 'هل يمكنني الإلغاء في أي وقت؟'],
            'answer' => ['en' => 'Yes, from your account page.', 'ar' => null],
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $created = FaqItem::latest('id')->firstOrFail();
    expect($created->sort)->toBe(7);

    $this->getJson('/api/v1/home?lang=ar')
        ->assertJsonPath('data.sections.11.items.6.question', 'هل يمكنني الإلغاء في أي وقت؟')
        ->assertJsonPath('data.sections.11.items.6.answer', 'Yes, from your account page.');

    Livewire::test(ManageFaqItems::class)
        ->callAction(TestAction::make('edit')->table($created), ['question' => ['en' => 'Can I cancel at any time?', 'ar' => 'هل يمكنني الإلغاء في أي وقت؟']])
        ->assertHasNoFormErrors();

    $ids = FaqItem::orderBy('sort')->pluck('id')->all();
    Livewire::test(ManageFaqItems::class)->call('reorderTable', array_map('strval', [$created->id, ...array_diff($ids, [$created->id])]));

    $this->getJson('/api/v1/home')->assertJsonPath('data.sections.11.items.0.question', 'Can I cancel at any time?');

    Livewire::test(ManageFaqItems::class)->callAction(TestAction::make('delete')->table($created));

    expect(FaqItem::find($created->id))->toBeNull();
    $this->getJson('/api/v1/home')->assertJsonCount(6, 'data.sections.11.items');
});
