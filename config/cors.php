<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Only the site may call the API from a browser; CORS_ALLOWED_ORIGINS (comma separated) adds more,
    // e.g. a preview deployment. Outside production any origin is allowed for local frontends.
    'allowed_origins' => env('APP_ENV', 'production') === 'production'
        ? array_values(array_filter([rtrim((string) env('FRONTEND_URL', 'https://blexchat-screens.vercel.app'), '/'), ...explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))]))
        : ['*'],

    // The design site (blexchat-screens) and its Vercel previews, where each PR is tried before the client's site.
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'production'
        ? ['#^https://blexchat-screens(-[a-z0-9-]+-issas-projects-d490f2f1)?\.vercel\.app$#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
