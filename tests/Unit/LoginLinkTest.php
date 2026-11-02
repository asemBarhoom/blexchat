<?php

use App\Models\LoginLink;

it('is usable until it expires', function () {
    expect((new LoginLink(['expires_at' => now()->addMinute()]))->isUsable())->toBeTrue()
        ->and((new LoginLink(['expires_at' => now()->subSecond()]))->isUsable())->toBeFalse();
});

it('is not usable once used, even before it expires', function () {
    $link = new LoginLink(['expires_at' => now()->addMinutes(10), 'used_at' => now()]);

    expect($link->isUsable())->toBeFalse();
});

it('matches what the frontend promises the visitor', function () {
    expect(LoginLink::LIFETIME_MINUTES)->toBe(15)
        ->and(LoginLink::RESEND_AFTER_SECONDS)->toBe(59)
        ->and(LoginLink::MODES)->toBe(['signup', 'signin']);
});
