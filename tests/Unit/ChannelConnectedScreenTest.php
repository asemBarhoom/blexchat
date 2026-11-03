<?php

use App\Models\ChannelConnectedScreen;

it('falls back to English for a text with no Arabic', function () {
    $screen = new ChannelConnectedScreen(['title' => ['en' => 'Your TikTok is connected!']]);

    expect($screen->getTranslation('title', 'ar'))->toBe('Your TikTok is connected!');
});

it('translates every text of the screen but not the platform', function () {
    expect((new ChannelConnectedScreen)->getTranslatableAttributes())
        ->toContain('channel_name', 'title', 'lead', 'account_kind', 'status_label', 'continue_label', 'connect_another_label')
        ->not->toContain('platform', 'active');
});
