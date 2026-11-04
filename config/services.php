<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Google sign-in. In Google Cloud, create an OAuth client (Web application) and add
    | FRONTEND_URL/verify/confirm as an authorised redirect URI.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Empty: FRONTEND_URL/verify/confirm. For testing without the frontend: APP_URL/api/v1/auth/google/callback.
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    | Instagram API with Instagram Login. Create the app at developers.facebook.com, add the
    | Instagram product, and point its webhook at /api/v1/webhooks/instagram.
    */
    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
        'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v21.0'),
    ],

    /*
    | Which payment page paid plans use. `paddle` is Paddle Billing. `fake` is a page this app serves,
    | with "Pay (test)" and "Decline" buttons: no keys, no network, for development. It is refused in
    | production unless PAYMENT_ALLOW_FAKE=true (a development server on Cloud).
    */
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) ? 'fake' : 'paddle'),
        'allow_fake' => (bool) env('PAYMENT_ALLOW_FAKE', false),
        'currency' => env('PAYMENT_CURRENCY', 'usd'),
    ],

    /*
    | Paddle Billing (sandbox-vendors.paddle.com while testing). The API key needs transaction.write
    | and subscription.write; the client-side token (test_… / live_…) opens the checkout in
    | Paddle.js on GET /payments/paddle, which must be the dashboard's default payment link.
    */
    'paddle' => [
        'environment' => env('PADDLE_ENV', 'sandbox'),
        'api_key' => env('PADDLE_API_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'client_token' => env('PADDLE_CLIENT_TOKEN'),
        // Seconds a signed webhook stays valid, against replays (Paddle's own default is 5).
        'webhook_tolerance' => 5,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
