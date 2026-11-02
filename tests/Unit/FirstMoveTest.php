<?php

use App\Models\FirstMove;

it('opens the wizard on its trigger', function () {
    expect((new FirstMove(['trigger' => 'dm']))->wizardPath())->toBe('/onboarding/automation/new?trigger=dm');
});

it('opens the wizard on its template too, encoded like the frontend does', function () {
    expect((new FirstMove(['trigger' => 'comment', 'template' => 'price link']))->wizardPath())
        ->toBe('/onboarding/automation/new?trigger=comment&template=price%20link');
});
