<?php

return [

    'connect' => [
        'account_taken' => 'هذا الحساب مربوط بالفعل بمساحة عمل أخرى في Blexchat. اطلب الوصول من مالكها، أو اربط حساباً آخر.',
        'access_denied' => 'لم يُربط :channel. أغلقت النافذة أو لم تسمح بالوصول.',
        'account_ineligible' => 'هذا حساب شخصي. يعمل Blexchat مع الحسابات الاحترافية — حساب أعمال أو صانع محتوى.',
        'provider_error' => 'رفض :channel الربط. حاول مرة أخرى.',
        'state_invalid' => 'انتهت صلاحية تسجيل الدخول إلى :channel أو بدأه حساب آخر. حاول مرة أخرى.',
    ],
    'access_request' => [
        'token_invalid' => 'انتهت صلاحية هذا الطلب. اربط الحساب مرة أخرى لتطلب الوصول.',
    ],
    'fake' => [
        'title' => 'ربط إنستغرام (تجربة)',
        'switch' => 'English',
        'badge' => 'وضع الاختبار — لا يُستخدم أي حساب إنستغرام',
        'intro' => 'هذه الصفحة بديل تسجيل الدخول إلى إنستغرام على خوادم التطوير. اختر كيف يرد إنستغرام.',
        'allow' => 'سماح — حساب احترافي',
        'personal' => 'حساب شخصي (مرفوض)',
        'deny' => 'إلغاء',
    ],

    'already_connected' => 'هذا الحساب مربوط بالفعل.',
    'disconnected' => 'فُصلت القناة.',
    'oauth_not_configured' => 'ربط هذه المنصة غير متاح على هذا الخادم بعد.',
    'oauth_state_invalid' => 'انتهت جلسة الربط. جرّب مرة أخرى.',
    'oauth_no_account' => 'لم تُرسل المنصة معرّف الحساب. جرّب مرة أخرى.',
    'test_event_unsupported' => 'على :platform يمكن تجربة الرسائل والتعليقات فقط.',
    'not_delivered' => 'لم يوصل :platform الرسالة. جرّب مرة أخرى بعد قليل.',
    'not_live' => 'الرد على :platform غير متاح بعد.',
    'delivery_failed_again' => 'لم تُرسل الرسالة هذه المرة أيضاً. جرّب بعد قليل.',
    'platforms' => [
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger',
        'tiktok' => 'TikTok',
    ],
];
