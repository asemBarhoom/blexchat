<?php

beforeEach(function () {
    config(['cors.allowed_origins' => ['https://site.test']]);
});

it('lets the site call the API from a browser', function () {
    $this->getJson('/api/v1/health', ['Origin' => 'https://site.test'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://site.test');
});

it('does not let another site call the API from a browser', function () {
    $allowed = $this->getJson('/api/v1/health', ['Origin' => 'https://evil.test'])->headers->get('Access-Control-Allow-Origin');

    expect($allowed)->not->toBe('https://evil.test')->not->toBe('*');
});
