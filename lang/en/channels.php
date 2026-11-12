<?php

return [

    'connect' => [
        'account_taken' => 'This account is already connected to another Blexchat workspace. Ask its owner for access, or connect a different account.',
        'access_denied' => ':channel wasn’t connected. You closed the window or didn’t allow access.',
        'account_ineligible' => 'This is a personal account. Blexchat works with professional accounts — Business or Creator.',
        'provider_error' => ':channel refused the connection. Try again.',
        'state_invalid' => 'The :channel sign-in expired or was started by another account. Try again.',
    ],
    'access_request' => [
        'token_invalid' => 'This request expired. Connect the account again to ask for access.',
    ],
    'fake' => [
        'title' => 'Connect Instagram (test)',
        'switch' => 'العربية',
        'badge' => 'Test mode — no Instagram account is used',
        'intro' => 'This page stands in for Instagram’s sign-in on development servers. Pick how Instagram answers.',
        'allow' => 'Allow — professional account',
        'personal' => 'Personal account (refused)',
        'deny' => 'Cancel',
    ],

    'already_connected' => 'This account is already connected.',
    'limit' => 'Your plan allows :count channel. Upgrade to connect more.|Your plan allows :count channels. Upgrade to connect more.',
    'disconnected' => 'Channel disconnected.',
    'oauth_not_configured' => 'Connecting this platform isn’t set up on this server yet.',
    'oauth_state_invalid' => 'The connection session expired. Try again.',
    'oauth_no_account' => 'The platform didn’t send an account ID. Try again.',
    'provider_pending' => 'Connecting :channel is waiting for :provider approval. We\'ll switch it on as soon as it lands.',
    'test_event_unsupported' => 'Only messages and comments can be simulated on :platform.',
    'not_delivered' => ':platform didn’t deliver the message. Try again in a moment.',
    'not_live' => 'Replies on :platform aren’t available yet.',
    'delivery_failed_again' => 'The message still wasn’t sent. Try again in a moment.',
    'platforms' => [
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger',
        'tiktok' => 'TikTok',
    ],
];
