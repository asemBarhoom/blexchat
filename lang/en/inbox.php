<?php

return [
    // Why an automation handed a conversation over to the owner ("Handed over to you").
    'handover' => [
        'unmatched' => 'Asked something the automation does not answer.',
    ],

    // Setting a reminder on Free (the Reminders folder is a paid feature).
    'reminders_plan' => 'Reminders need a paid plan. Upgrade to set one.',

    // The email sent when a reminder is due.
    'reminder_due' => [
        'subject' => 'Reminder: your conversation with :name',
        'line' => 'You asked to be reminded about your conversation with :name. It is waiting for you in the inbox.',
        'button' => 'Open the inbox',
    ],
];
