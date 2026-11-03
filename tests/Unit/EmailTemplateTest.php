<?php

use App\Models\EmailTemplate;

it('finds each placeholder once, in order of first use', function () {
    expect(EmailTemplate::placeholdersIn('Use :code within :minutes minutes (:minutes, not 10:30).'))->toBe(['code', 'minutes'])
        ->and(EmailTemplate::placeholdersIn('Note: no placeholders at https://blexchat.com'))->toBe([]);
});

it('fills placeholders without touching the values it puts in', function () {
    expect(EmailTemplate::fillPlaceholders('Works for :minutes minutes, :name.', ['minutes' => 15, 'name' => ':minutes']))
        ->toBe('Works for 15 minutes, :minutes.')
        ->and(EmailTemplate::fillPlaceholders('No placeholders.', ['minutes' => 15]))->toBe('No placeholders.');
});

it('requires the placeholders the English default uses, and allows only the template’s', function () {
    expect(EmailTemplate::requiredPlaceholders('login_link.signin', 'expiry'))->toBe(['minutes'])
        ->and(EmailTemplate::requiredPlaceholders('login_link.signin', 'subject'))->toBe([])
        ->and(EmailTemplate::allowedPlaceholders('login_link.signup'))->toBe(['minutes']);
});

it('has a default lang/ text for every field of every template, in each language', function () {
    foreach (EmailTemplate::TEMPLATES as $langKeys) {
        foreach ($langKeys as $langKey) {
            foreach (config('app.supported_locales') as $locale) {
                expect(EmailTemplate::defaultText($langKey, $locale))->not->toBe($langKey);
            }
        }
    }
});
