# Backend update for Frontend

تم رفع آخر تحديثات الـ Backend على `master`، وكل التغييرات متاحة على السيرفر بعد سحب آخر commit وتشغيل migrations.

## ما تم تحديثه

- إصلاح إرسال رسائل Instagram المؤتمتة.
- دعم الأتمتة التجريبية على WhatsApp وMessenger وTikTok.
- دعم تعليقات Messenger وTikTok التجريبية.
- تجهيز OAuth التجريبي للقنوات الثلاث مع التحقق من `state` ومنع إعادة استخدامه.
- إضافة حماية Webhooks من التكرار وتسجيل الأحداث المقبولة والمرفوضة.
- توحيد أخطاء إرسال الرسائل:

```json
{
  "message": "Messenger did not deliver the message.",
  "code": "provider_unavailable",
  "platform": "messenger",
  "retryable": true
}
```

- إضافة إعادة محاولة للإرسال:
  - `POST /api/v1/message-delivery-attempts/{attempt}/retry`
  - استجابة فشل الإرسال تحتوي `attempt_id` لاستخدامه في الرابط.
  - استجابة إعادة المحاولة تحتوي `attempt_id` و`attempts`، وتعمل لصاحب المحادثة (كانت ترجع 403 لكل أحد).
- إضافة فحص القناة:
  - `GET /api/v1/channels/{channel}/health`
- إضافة حالة OAuth:
  - `GET /api/v1/channels/oauth/status`
- إضافة مركز المساعدة:
  - `GET /api/v1/help`
- إضافة فحص النظام:
  - `GET /api/v1/health`
- إضافة إعدادات AI داخل `automations.extras`:
  - `ai_goal`
  - `ai_comments`
  - `review_before_send`
- تحديث Swagger في `storage/api-docs/api-docs.json`.

قيم AI الحالية تُحفظ كما يرسلها الفرونت داخل `extras.ai_goal`، وتُعاد كاملة ضمن `extras` في كل Automation resource.

## المطلوب من Frontend

1. استخدام `code` و`retryable` بدل الاعتماد على نص رسالة الخطأ.
2. عند `retryable=true` عرض زر إعادة المحاولة باستخدام endpoint إعادة الإرسال.
3. عرض حالة القناة من endpoint `channels/{id}/health`، خصوصًا `ready` و`send_supported`.
4. عدم إظهار إرسال TikTok كميزة جاهزة طالما `send_supported=false`.
5. ربط شاشة OAuth مع `channels/oauth/status` قبل عرض زر الربط.
6. ربط إعدادات AI بالحقول الجديدة داخل `extras`.
7. عرض Help Center من `/help` بدل نص ثابت داخل الواجهة.
8. التعامل مع Webhook وOAuth errors عبر الأكواد، ومنها:
   - `channel_not_live`
   - `provider_unavailable`
   - `provider_rejected`
   - `invalid_signature`
   - `plan_required`
   - `live_limit`
9. استخدام `meta.total` و`meta.live` في قوائم الأتمتة والمحادثات وعدم تثبيت الأرقام داخل الواجهة.
10. تحديث types/API client من ملف Swagger الجديد.

## ملاحظات تشغيل

- لا تستخدم `migrate:fresh` على السيرفر.
- بعد النشر شغّل `php artisan migrate --force`.
- شغّل `php artisan l5-swagger:generate` عند الحاجة لتحديث Swagger.
- TikTok وOAuth الحقيقيان يحتاجان credentials وتفعيل التطبيقات الخارجية؛ النسخة المحلية تستخدم fake drivers.
