<?php

use App\Models\FirstMove;
use App\Models\User;
use Database\Seeders\FirstMoveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FirstMoveSeeder::class);
    $this->user = User::factory()->create();
});

describe('GET /api/v1/onboarding/first-moves', function () {
    it('lists the first moves the frontend shows, in order', function () {
        $this->actingAs($this->user)->getJson('/api/v1/onboarding/first-moves')
            ->assertOk()
            ->assertJsonPath('data.*.id', ['comment-dm', 'dm-replies'])
            ->assertJsonPath('data.0', [
                'id' => 'comment-dm',
                'trigger' => 'comment',
                'template' => null,
                'wizard_path' => '/onboarding/automation/new?trigger=comment',
                'title' => 'Auto-DM links from comments',
                'line' => 'Someone comments a keyword on your post — they get your link in a DM, instantly.',
                'incoming' => ['who' => 'reem.k', 'text' => 'LINK 😍 how much?', 'kind' => 'Comment'],
                'reply' => 'Here’s the link 👉 nourboutique.com/abaya',
            ]);
    });

    it('answers in Arabic with ?lang=ar', function () {
        $this->actingAs($this->user)->getJson('/api/v1/onboarding/first-moves?lang=ar')
            ->assertJsonPath('data.1.title', 'ردّ على كل رسائلك الخاصة')
            ->assertJsonPath('data.1.incoming.kind', 'رسالة خاصة')
            ->assertJsonPath('data.1.incoming.who', 'faisal.a');
    });

    it('hides moves switched off and follows the admin order', function () {
        FirstMove::where('key', 'comment-dm')->update(['active' => false]);
        FirstMove::factory()->create(['key' => 'story-replies', 'sort' => -1]);

        $this->actingAs($this->user)->getJson('/api/v1/onboarding/first-moves')
            ->assertJsonPath('data.*.id', ['story-replies', 'dm-replies']);
    });

    it('needs a signed-in owner', function () {
        $this->getJson('/api/v1/onboarding/first-moves')->assertUnauthorized();
    });
});

it('seeds the first moves only once when run again', function () {
    $this->seed(FirstMoveSeeder::class);

    expect(FirstMove::count())->toBe(2);
});
