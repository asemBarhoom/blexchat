<?php

use Illuminate\Support\Facades\Storage;

it('uploads the bundled marketing images to the public disk once', function () {
    $disk = Storage::fake('public');

    $this->artisan('marketing:publish-assets')->assertSuccessful();

    $disk->assertExists('marketing/home/hero-photo.webp');
    $disk->assertExists('marketing/home/avatars/sara.webp');

    $disk->put('marketing/home/hero-photo.webp', 'changed');
    $this->artisan('marketing:publish-assets')->expectsOutput('Uploaded 0 file(s).');
    expect($disk->get('marketing/home/hero-photo.webp'))->toBe('changed');

    $this->artisan('marketing:publish-assets --force')->assertSuccessful();
    expect($disk->get('marketing/home/hero-photo.webp'))->not->toBe('changed');
});
