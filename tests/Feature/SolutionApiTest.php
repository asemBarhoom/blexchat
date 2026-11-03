<?php

use App\Models\Solution;
use App\Models\SolutionAutomation;
use App\Models\UseCase;
use Database\Seeders\HomeContentSeeder;
use Database\Seeders\SolutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists every visible page in order, with linked pages named by their card', function () {
    $this->seed([HomeContentSeeder::class, SolutionSeeder::class]);
    Solution::forSlug('support')->firstOrFail()->update(['active' => false]);

    $this->getJson('/api/v1/solutions?lang=ar')
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0', [
            'slug' => 'creators',
            'name' => 'صنّاع المحتوى',
            'shape' => 'rounded',
            'tone' => 'brand',
            'headline' => 'تعليقاتك قائمة انتظار.',
        ])
        ->assertJsonPath('data.6.slug', 'education')
        ->assertJsonPath('data.6.tone', 'tint-ink');
});

it('serves a whole page shaped like the frontend', function () {
    $this->seed([HomeContentSeeder::class, SolutionSeeder::class]);

    $response = $this->getJson('/api/v1/solutions/e-commerce')->assertOk();

    $response->assertJsonPath('data.slug', 'e-commerce')
        ->assertJsonPath('data.name', 'E-commerce')
        ->assertJsonPath('data.shape', 'blob')
        ->assertJsonPath('data.sample.handle', 'nourboutique')
        ->assertJsonPath('data.sample.avatar', url('storage/marketing/home/avatars/sara.webp'))
        ->assertJsonPath('data.hero.headline', 'The price is asked a hundred times. Answer it once.')
        ->assertJsonCount(3, 'data.automations')
        ->assertJsonPath('data.automations.0.trigger', ['source' => 'comment', 'words' => ['السعر']])
        ->assertJsonPath('data.automations.0.button', 'شوفي المنتج')
        ->assertJsonPath('data.automations.0.post.image', url('storage/marketing/home/creators/huda.webp'))
        ->assertJsonPath('data.automations.1.post.tile', ['text' => 'خصم ٢٠٪', 'tone' => 'ink'])
        ->assertJsonMissingPath('data.automations.2.post')
        ->assertJsonMissingPath('data.automations.0.account')
        ->assertJsonPath('data.steps.0', "Connect your shop's Instagram or WhatsApp.")
        ->assertJsonPath('data.faq.0.q', 'Does it take the payment?')
        ->assertJsonPath('data.closing', 'Turn the next “how much?” into an order.');
});

it('serves the page copy in Arabic and keeps the conversation as typed', function () {
    $this->seed([HomeContentSeeder::class, SolutionSeeder::class]);

    $this->getJson('/api/v1/solutions/agencies?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.name', 'الوكالات')
        ->assertJsonPath('data.automations.0.title', 'ردود يوم الإطلاق')
        ->assertJsonPath('data.automations.0.customer', 'السعر؟')
        ->assertJsonPath('data.automations.0.account.handle', 'rawhperfumes')
        ->assertJsonPath('data.faq.0.q', 'هل أستطيع إدارة عدة حسابات عملاء من تسجيل دخول واحد؟');
});

it('falls back to English for a text not translated yet', function () {
    Solution::factory()->create(['slug' => 'gyms', 'headline' => ['en' => 'Only in English'], 'closing' => ['en' => 'Bye', 'ar' => 'مع السلامة']]);

    $this->getJson('/api/v1/solutions/gyms?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.hero.headline', 'Only in English')
        ->assertJsonPath('data.closing', 'مع السلامة');
});

it('finds a linked page by its card slug and follows the card when it changes', function () {
    $useCase = UseCase::factory()->create(['slug' => 'gyms', 'name' => ['en' => 'Gyms'], 'tone' => 'ink']);
    Solution::factory()->forUseCase($useCase)->create();

    $this->getJson('/api/v1/solutions/gyms')->assertOk()->assertJsonPath('data.name', 'Gyms')->assertJsonPath('data.tone', 'ink');

    $useCase->update(['slug' => 'fitness']);

    $this->getJson('/api/v1/solutions/gyms')->assertNotFound();
    $this->getJson('/api/v1/solutions/fitness')->assertOk()->assertJsonPath('data.slug', 'fitness');
});

it('answers 404 for an unknown or hidden page', function () {
    Solution::factory()->inactive()->create(['slug' => 'hidden']);

    $this->getJson('/api/v1/solutions/hidden')->assertNotFound();
    $this->getJson('/api/v1/solutions/nothing-here')->assertNotFound();
});

it('draws an avatar for an account with no image, and lists automations in admin order', function () {
    $solution = Solution::factory()->create(['slug' => 'gyms', 'sample_handle' => 'qimmagym']);
    SolutionAutomation::factory()->for($solution)->create(['sort' => 2, 'title' => ['en' => 'Third']]);
    SolutionAutomation::factory()->for($solution)->onComment()->create(['sort' => 0, 'title' => ['en' => 'First']]);

    $this->getJson('/api/v1/solutions/gyms')
        ->assertOk()
        ->assertJsonPath('data.sample.avatar', fn (string $avatar): bool => str_starts_with($avatar, 'data:image/svg+xml,'))
        ->assertJsonPath('data.automations.0.title', 'First')
        ->assertJsonPath('data.automations.0.post.tile', ['text' => 'السعر', 'tone' => 'pink'])
        ->assertJsonPath('data.automations.1.title', 'Third');
});

it('seeds again without duplicating anything', function () {
    $this->seed([HomeContentSeeder::class, SolutionSeeder::class, SolutionSeeder::class]);

    expect(Solution::count())->toBe(8)
        ->and(SolutionAutomation::count())->toBe(24)
        ->and(Solution::whereNotNull('use_case_id')->count())->toBe(6)
        ->and(Solution::forSlug('education')->firstOrFail()->use_case_id)->toBeNull();
});
