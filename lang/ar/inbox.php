<?php

return [
    'branding' => '⚡ أُرسلت عبر Blexchat',
    // Why an automation handed a conversation over to the owner ("Handed over to you").
    'handover' => [
        'unmatched' => 'سأل عن شيء لا تجيب عنه الأتمتة.',
        'ai_unsure' => 'لم يكن Blexchat AI متأكداً من الإجابة.',
    ],

    // Setting a reminder on Free (the Reminders folder is a paid feature).
    'reminders_plan' => 'التذكيرات تحتاج باقة مدفوعة. رقِّ باقتك لتضبط تذكيراً.',

    // The email sent when a reminder is due.
    'reminder_due' => [
        'subject' => 'تذكير: محادثتك مع :name',
        'line' => 'طلبت أن نذكّرك بمحادثتك مع :name. هي بانتظارك في صندوق الوارد.',
        'button' => 'افتح صندوق الوارد',
    ],
];
