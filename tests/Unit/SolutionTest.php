<?php

use App\Models\Solution;
use App\Models\SolutionAutomation;
use App\Models\UseCase;

it('reads its identity from its use case when linked, and from itself otherwise', function () {
    $useCase = new UseCase(['slug' => 'clinics', 'shape' => 'circle']);
    $linked = (new Solution)->setRelation('useCase', $useCase);
    $standalone = (new Solution(['slug' => 'support', 'shape' => 'circle']))->setRelation('useCase', null);

    expect($linked->identity())->toBe($useCase)
        ->and($standalone->identity())->toBe($standalone)
        ->and($standalone->identity()->slug)->toBe('support');
});

it('draws the initial of the handle on the colours when there is no image', function () {
    $avatar = Solution::avatarUrl(null, '@bait.tech', '#0f7a5a', '#ffffff');
    $svg = rawurldecode(substr($avatar, strlen('data:image/svg+xml,')));

    expect($avatar)->toStartWith('data:image/svg+xml,')
        ->and($svg)->toContain('fill="#0f7a5a"')
        ->and($svg)->toContain('fill="#ffffff">B</text>');
});

it('uses the uploaded image when there is one', function () {
    expect(Solution::avatarUrl('marketing/solutions/uploads/a.png', 'x', '#000000', '#ffffff'))
        ->toBe(url('storage/marketing/solutions/uploads/a.png'));
});

it('has a post only with an image or a tile, and an account of its own only with a handle', function () {
    expect((new SolutionAutomation)->hasPost())->toBeFalse()
        ->and((new SolutionAutomation(['post_tile_text' => 'السعر']))->hasPost())->toBeTrue()
        ->and((new SolutionAutomation(['post_image' => 'a.png']))->hasPost())->toBeTrue()
        ->and((new SolutionAutomation)->hasOwnAccount())->toBeFalse()
        ->and((new SolutionAutomation(['account_handle' => 'qimmagym']))->hasOwnAccount())->toBeTrue();
});
