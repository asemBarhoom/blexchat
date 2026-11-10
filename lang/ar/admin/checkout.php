<?php

return [
    'fake_in_production' => 'الدفع التجريبي مرفوض في الإنتاج',
    'fake_in_production_note' => 'PAYMENT_DRIVER=fake على خادم إنتاج، فالباقات المدفوعة ترد بـ «الدفع غير متاح». اضبط PAYMENT_DRIVER=stripe، أو PAYMENT_ALLOW_FAKE=true على خادم تطوير فقط.',
    'fake_on' => 'الدفع التجريبي (PAYMENT_DRIVER=fake) مُفعّل',
    'fake_on_note' => 'لا يُخصم أي مبلغ: صفحة الدفع صفحة اختبار في هذا الخادم بزرّي «ادفع (اختبار)» و«رفض». :prices للدفع الحقيقي اضبط PAYMENT_DRIVER=stripe.',
    'live' => 'الوضع الحقيقي (live)',
    'nav' => 'إعدادات الدفع',
    'plans' => 'الباقات المعروضة في صفحة الدفع',
    'plans_help' => 'الباقات التي يتنقّل بينها العميل في صفحة الدفع، بترتيب الباقات.',
    'prices_note' => 'الأسعار تُرسل من جدول الباقات مباشرة (السنوي = السعر الشهري للباقة السنوية × 12)، والكوبون يُخصم من أول دفعة.',
    'saved' => 'حُفظت إعدادات الدفع.',
    'stripe_off' => 'الدفع بالبطاقة (Stripe) غير مُفعّل',
    'stripe_off_note' => 'أضف STRIPE_SECRET وSTRIPE_WEBHOOK_SECRET إلى متغيرات البيئة. حتى ذلك الحين، الباقات المدفوعة ترد بـ «الدفع غير متاح» والتجربة المجانية تعمل كالمعتاد. عنوان الـ webhook: :webhook',
    'stripe_on' => 'الدفع بالبطاقة (Stripe) مُفعّل — :mode',
    'stripe_on_note' => ':prices في Stripe أضف webhook إلى :webhook للأحداث: checkout.session.completed وcheckout.session.expired وinvoice.paid وcustomer.subscription.deleted.',
    'test' => 'وضع الاختبار (test)',
    'title' => 'إعدادات صفحة الدفع',
    'trial_reminder_days' => 'يوم تذكير التجربة المجانية',
    'trial_reminder_help' => 'في أي يوم من التجربة المجانية نرسل بريد التذكير. يجب أن يسبق نهاية أقصر تجربة (:days يوماً).',
    'webhook_secret_missing' => 'تنبيه: STRIPE_WEBHOOK_SECRET فارغ، فلن تُقبل أحداث Stripe.',
];
