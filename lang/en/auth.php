<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'link' => [
        'signup' => [
            'subject' => 'Confirm your email',
            'line' => 'Tap the button to confirm your email and finish setting up your Blexchat account.',
            'button' => 'Confirm my email',
        ],
        'signin' => [
            'subject' => 'Your sign-in link',
            'line' => 'Tap the button to sign in to Blexchat. No password needed.',
            'button' => 'Sign in to Blexchat',
        ],
        'expiry' => 'This link works for :minutes minutes.',
        'ignore' => 'If you didn’t ask for this, you can ignore this email — nothing happens until the link is opened.',
        'sent' => 'Check your email.',
        'resend_wait' => 'You can resend the link in :seconds seconds.',
        'too_many' => 'Too many attempts. Try again in a few minutes.',
        'expired' => 'This link has expired. Sign-in links work once, for :minutes minutes.',
    ],
    'google_failed' => 'Google sign-in didn’t finish. Try again, or continue with email.',
    'signed_out' => 'You are signed out.',

];
