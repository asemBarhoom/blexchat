# تسليم تحديثات الـ Backend للـ Frontend

مرحبًا، تم رفع آخر تحديثات الـ Backend على `master`.

**آخر commit:** `b6acfcb`

يرجى تحديث الـ API client من ملف Swagger:

```text
storage/api-docs/api-docs.json
```

## ردًا على الملاحظات الأخيرة

### 1. رقم محاولة إعادة الإرسال

عند فشل إرسال رسالة، يعيد الـ API استجابة `502` تحتوي على:

```json
{
  "message": "Messenger did not deliver the message.",
  "code": "provider_rejected",
  "platform": "messenger",
  "retryable": true,
  "attempt_id": 42,
  "attempts": 1,
  "error": "..."
}
```

إعادة الإرسال:

```http
POST /api/v1/message-delivery-attempts/{attempt_id}/retry
```

النجاح:

```json
{
  "data": {
    "attempt_id": 42,
    "status": "sent",
    "attempts": 2,
    "platform_message_id": "provider-message-id"
  }
}
```

إذا كانت المحاولة أُرسلت سابقًا، يعيد الـ API `idempotent: true` ولا يرسل رسالة ثانية.

أصلحنا صلاحيات endpoint ليعمل مع مالك المحادثة والـ teammate المسموح له بالوصول.

### 2. `extras` في الأتمتة

كل Automation resource يعيد `extras` كاملة في القائمة والتفاصيل والإنشاء والتعديل والاسترجاع.

التحديث:

```http
PATCH /api/v1/automations/{id}
```

مثال:

```json
{
  "extras": {
    "ai_goal": "Increase qualified leads",
    "ai_comments": true,
    "review_before_send": false
  }
}
```

يتم دمج القيم الجديدة مع القيم الموجودة بدل استبدال كامل الكائن.

### 3. قيم AI

- `ai_goal`: نص حر، nullable، حد أقصى 500 حرف.
- `ai_comments`: boolean.
- `review_before_send`: boolean.

هذه القيم محفوظة ومُعادة عبر API. محرك AI لا يفعّل سلوك التعليقات أو المراجعة بناءً عليها حتى الآن؛ لا تعرضوها كميزة تشغيلية فعالة قبل تأكيد تفعيلها.

## العقود والـ endpoints الجديدة

### القنوات والإرسال

- `GET /api/v1/channels/{channel}/health`
  - يعيد `ready`, `status`, `token_expires_at`, `send_supported`.
  - لا تعرضوا إرسال TikTok كميزة جاهزة عندما تكون `send_supported=false`.
- أخطاء الإرسال تعتمد على:
  - `code`
  - `platform`
  - `retryable`
  - `attempt_id` عند وجود محاولة محفوظة.

الأكواد المهمة:

```text
channel_not_live
provider_rejected
provider_unavailable
plan_required
owner_only
seat_limit
missing_columns
invitation_invalid
other_email
has_own_account
```

### OAuth

- `GET /api/v1/channels/oauth/{platform}/start`
- `GET /api/v1/channels/oauth/{platform}/callback`
- `GET /api/v1/channels/oauth/status`

المنصات: `messenger`, `whatsapp`, `tiktok`.

الـ fake driver متاح محليًا. OAuth الحقيقي يحتاج credentials وتفعيل التطبيقات الخارجية.

### Webhooks

- حماية من تكرار الحدث.
- تسجيل الأحداث المقبولة والمرفوضة.
- التوقيع غير الصحيح يسجل كـ `rejected` مع `invalid_signature`.

لا يحتاج الفرونت لمعالجة Webhook مباشرة، لكن يجب عرض أخطاء القنوات من الأكواد المعيارية.

### محتوى النظام

- `GET /api/v1/help` — Help Center وFAQ بحسب اللغة.
- `GET /api/v1/health` — حالة قاعدة البيانات وCache وQueue.
- `GET /api/v1/home` — يتضمن بيانات صفحة 404 في `not_found`:
  - `question`
  - `replied`
  - `answer`
  - `code`
  - `title`
  - `body`
  - `cta`

## المطلوب من Frontend

1. تحديث types وAPI client من Swagger الجديد.
2. استخدام `code` بدل تحليل نص `message`.
3. إظهار زر إعادة المحاولة فقط عندما `retryable=true`.
4. تمرير `attempt_id` إلى endpoint retry.
5. إظهار حالة القناة من `/channels/{id}/health`.
6. فحص `/channels/oauth/status` قبل عرض خيارات الربط.
7. ربط حقول AI مع `extras` مع إبقائها غير فعالة حتى تفعيل المحرك.
8. استخدام `meta.total` و`meta.live` في القوائم بدل تثبيت الأرقام.
9. استخدام `/help` بدل نص Help Center ثابت.
10. التعامل مع حالات `403`, `422`, `502` حسب `code` وبيانات الرد.
11. عدم اعتبار TikTok إرسالًا حقيقيًا إلا عندما يعيد الـ API `send_supported=true`.

## النشر والتحقق

بعد سحب آخر `master`:

```bash
git pull origin master
php artisan migrate --force
php artisan optimize:clear
```

ملف Swagger متعقّب في Git؛ لا حاجة لتوليده يدويًا بعد النشر. لا تستخدموا `migrate:fresh` على السيرفر.

لأي حقل ناقص أو سلوك غير واضح، أرسلوا endpoint والـ response الفعلي مع تفاصيل الحالة لنراجعه.
