<?php

use App\Models\AuthScreen;

it('fills the texts a language lacks from the fallback language, one by one', function () {
    $screen = new AuthScreen(['texts' => [
        'en' => ['title' => 'Welcome back', 'lead' => 'Sign in.'],
        'ar' => ['title' => 'أهلًا بعودتك', 'lead' => ''],
    ]]);

    expect($screen->textsIn('ar'))->toBe(['title' => 'أهلًا بعودتك', 'lead' => 'Sign in.'])
        ->and($screen->isMissingTextsIn('ar'))->toBeTrue()
        ->and($screen->isMissingTextsIn('en'))->toBeFalse();
});

it('is fully translated when every fallback text has a translation', function () {
    $screen = new AuthScreen(['texts' => [
        'en' => ['title' => 'Welcome back'],
        'ar' => ['title' => 'أهلًا بعودتك'],
    ]]);

    expect($screen->isMissingTextsIn('ar'))->toBeFalse()
        ->and($screen->textsIn('ar'))->toBe(['title' => 'أهلًا بعودتك']);
});
