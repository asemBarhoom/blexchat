<?php

return [
    // A teammate opened a page that is the owner's.
    'owner_only' => 'Only the account owner can do this.',
    // Settings · Team: inviting.
    'seat_limit' => 'Your plan has :limit inbox seats, and they are all in use. Upgrade to invite more teammates.',
    'already_member' => 'They are already on your team.',
    'own_email' => 'That is your own email.',
    // Accepting an invitation.
    'invitation_invalid' => 'This invitation has expired or was withdrawn. Ask for a new one.',
    'invitation_other_email' => 'This invitation was sent to :email. Sign in with that email to join.',
    'has_own_account' => 'This account already has its own channels or team, so it cannot join another team.',
    // Assigning a conversation.
    'assignment_plan' => 'Assigning conversations needs a plan with team roles. Upgrade to assign.',

    // The invitation email.
    'invite' => [
        'subject' => ':owner invited you to their Blexchat inbox',
        'line' => ':owner invited you to answer messages in the :business inbox on Blexchat.',
        'button' => 'Join the team',
        'expiry' => 'The invitation works for 7 days.',
    ],
];
