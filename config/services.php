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
        // meta: the real Graph API. fake: a test sign-in page, demo posts, logged replies (default on local).
        'driver' => env('INSTAGRAM_DRIVER', env('APP_ENV', 'production') === 'local' ? 'fake' : 'meta'),
        'allow_fake' => (bool) env('INSTAGRAM_ALLOW_FAKE', false),
    ],

    /*
    | Which payment page paid plans use. `fake` is a page this app serves, with "Pay (test)" and
    | "Decline" buttons: no keys, no network, for development (Stripe is not available everywhere).
    | It is refused in production unless PAYMENT_ALLOW_FAKE=true (a development server on Cloud).
    */
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) ? 'fake' : 'stripe'),
        'allow_fake' => (bool) env('PAYMENT_ALLOW_FAKE', false),
    ],

    /*
    | Card payments with Stripe Checkout (the hosted payment page), called over its REST API.
    | Use test-mode keys in development: sk_test_… from the dashboard, and the whsec_… that
    | `stripe listen` prints (or the webhook endpoint's signing secret on a deployed site).
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'usd'),
        // Seconds a signed webhook stays valid, against replays.
        'webhook_tolerance' => 300,
    ],

    /*
    | Blexchat AI: Claude answers what no automation keyword covers, from the business info the
    | owner writes, and hands the conversation over when it is not sure. Without a key it is off
    | (the fake driver, the default on local, answers without the network).
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'driver' => env('ANTHROPIC_DRIVER', env('APP_ENV', 'production') === 'local' ? 'fake' : 'claude'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
