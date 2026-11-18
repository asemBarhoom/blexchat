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

it('lets the design site and its Vercel previews call the API', function (string $origin) {
    config(['cors.allowed_origins_patterns' => require_patterns()]);

    $this->getJson('/api/v1/health', ['Origin' => $origin])
        ->assertHeader('Access-Control-Allow-Origin', $origin);
})->with([
    'https://blexchat-screens.vercel.app',
    'https://blexchat-screens-git-fix-x-issas-projects-d490f2f1.vercel.app',
    'https://blexchat-screens-abc123-issas-projects-d490f2f1.vercel.app',
]);

it('does not let a look-alike preview call the API', function (string $origin) {
    config(['cors.allowed_origins_patterns' => require_patterns()]);

    $allowed = $this->getJson('/api/v1/health', ['Origin' => $origin])->headers->get('Access-Control-Allow-Origin');

    expect($allowed)->not->toBe($origin);
})->with([
    'https://blexchat-screens-abc-someone-else.vercel.app',
    'https://blexchat-screens.vercel.app.evil.test',
    'http://blexchat-screens.vercel.app',
]);

/**
 * @return list<string> The production patterns, read the way the config file builds them.
 */
function require_patterns(): array
{
    $previous = $_ENV['APP_ENV'] ?? null;
    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
    $patterns = (require config_path('cors.php'))['allowed_origins_patterns'];
    putenv('APP_ENV='.$previous);
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $previous;

    return $patterns;
}
