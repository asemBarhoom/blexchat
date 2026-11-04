<?php

return [

    'connect' => [
        'account_taken' => 'This account is already connected to another Blexchat workspace. Ask its owner for access, or connect a different account.',
        'access_denied' => ':channel wasn’t connected. You closed the window or didn’t allow access.',
        'account_ineligible' => 'This is a personal account. Blexchat works with professional accounts — Business or Creator.',
        'provider_error' => ':channel refused the connection. Please try again.',
        'state_invalid' => 'The :channel sign-in expired or was started by another account. Please try again.',
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

];
