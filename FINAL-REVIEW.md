# Blexchat: Final Backend Review & Handover

وثيقة المراجعة النهائية والتسليم التقني لربط المنصات الخارجية، التطبيقات، والجاهزية التشغيلية للباك إند.

---

## 1. قائمة الحسابات والتطبيقات المنشأة (Created Accounts & Apps)

### أ. Meta for Developers & Business
- **التطبيق (Meta App):** `Blexchat` (App ID: `1396711619210277`).
- **الحافظة التجارية (Business Portfolio):** `ASEM`.
- **حالة التطبيق:** منشور (Published / Live Mode).
- **المنتجات المفعّلة بالتطبيق:**
  1. **Facebook Login for Business:** يُستخدم لمصادقة صفحات Messenger وربط حسابات Instagram وWhatsApp عبر OAuth 2.0.
  2. **Instagram Graph API:** إدارة التعليقات، قراءة المنشورات، وإرسال الرسائل المباشرة عبر الـ Webhook والـ API.
  3. **Messenger Platform:** اشتراك الصفحات عبر `/{page_id}/subscribed_apps` لقنوات `messages` و `feed`.
  4. **WhatsApp Business Platform:** ربط الأرقام عبر Embedded Signup واكتشاف الـ WABA والاشتراك التلقائي.
- **مسارات الـ Webhooks في الباك إند:**
  - Instagram: `https://<api-domain>/api/v1/webhooks/instagram`
  - Messenger: `https://<api-domain>/api/v1/webhooks/messenger`
  - WhatsApp: `https://<api-domain>/api/v1/webhooks/whatsapp`
  - رمز التحقق الموحد: `META_WEBHOOK_VERIFY_TOKEN` (يدعم الباك إند كلاً من `hub_mode` و `hub.mode`).

---

### ب. TikTok for Developers
- **التطبيق (TikTok App):** تطبيق مسجل في بوابة TikTok for Developers.
- **الصلاحيات ومسار الدخول:**
  - Login Kit مع Scopes: `user.info.basic,video.list`.
  - Redirect URI: `https://<api-domain>/api/v1/channels/oauth/tiktok/callback`.
  - جلب مقاطع الفيديو: مدعوم برمجياً عبر `TikTokClient` وقراءة قائمة المقاطع من `/v2/video/list/`.
- **حالة المراسلة:** مراسلة TikTok والرد على التعليقات معزولة بأمان (`ChannelOAuthProvider::messagingAvailable('tiktok') = false`)، ومحاولات تفعيل الأتمتة تُرفض برمز منظم `channel_not_live`.

---

### ج. Stripe (المدفوعات والاشتراكات)
- **بوابة الدفع:** دعم Stripe رسمي عبر `StripeClient`.
- **المنتجات والباقات:** مطابقة باقات النظام (Free, Pro, Business, Advanced) شهرياً وسنوياً، مع التحقق الصارم من صحة الأسعار قبل إنشاء جلسة الـ Checkout.
- **الـ Webhook:** `https://<api-domain>/api/v1/webhooks/stripe` للتعامل مع أحداث تجديد الاشتراكات والفشل والإلغاء.
- **مؤشر الجاهزية:** فحص جاهزية الإنتاج عبر `StripeClient::isReadyForProduction()`.

---

### د. Anthropic / Claude AI (Blexchat AI)
- **المحرك:** `ClaudeClient` مع الموديل الافتراضي `claude-opus-5-5`.
- **الإعدادات والتأريض:** تأريض إجابات الذكاء الاصطناعي بناءً على بيانات المنشأة المخزنة في `/api/v1/ai/settings`.
- **التحويل البشري:** عند عدم التأكد من الإجابة، يتم التحويل التلقائي للموظف البشري عبر `Conversation::handOver` مع تسجيل السبب (`reason`).

---

## 2. الموافقات والاعتمادات المعلقة (Pending Approvals & Credentials)

| المنصة | المتطلب | الحالة الحالية | الإجراء المطلوب قبل الإطلاق العام |
|---|---|---|---|
| **Meta** | توثيق المنشأة (Business Verification) | معلق | إرفاق السجل التجاري أو كشف حساب بنكي رسمي باسم الشركة وعنوانها من لوحة Meta Business Suite. |
| **Meta** | مراجعة التطبيق (App Review) | جاهز للتقديم | طلب الوصول المتقدم (Advanced Access) للصلاحيات: `instagram_basic`, `instagram_manage_comments`, `instagram_manage_messages`, `pages_show_list`, `pages_manage_metadata`, `pages_read_engagement`, `pages_manage_engagement`, `business_management` (تم إعداد نصوص التقديم والسيناريوهات كاملة في `META-APP-REVIEW.md`). |
| **WhatsApp** | توثيق رقم رسمي (Official Phone Number) | Sandbox / Test | شراء رقم نشاط تجاري وتفعيله عبر WABA الرسمي بعد استكمال توثيق المنشأة. |
| **TikTok** | Business Messaging & Comment API | معلق | التقديم على برنامج الشركاء أو TikTok for Business للحصول على صلاحيات المراسلة المباشرة. |
| **البريد** | مزود إرسال الإنتاج (Resend / Mailtrap Sending) | تجريبي (Mailtrap Sandbox) | ضبط SPF وDKIM وDMARC على دومين الإنتاج، وإدخال مفاتيح المزود على Laravel Cloud. |
| **أسرار الإنتاج** | Environment Variables | مكتمل محلياً | التأكد من إدخال `META_APP_SECRET`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `ANTHROPIC_API_KEY`, `TIKTOK_CLIENT_SECRET` في بيئة الإنتاج على Laravel Cloud. |

---

## 3. الخطوات التنفيذية التالية للباك إند (Next Implementation Steps)

1. **إدارة الـ Queue والـ Scheduler في بيئة الإنتاج (Laravel Cloud):**
   - تشغيل الـ Scheduler المجدول دقيقة بدقيقة لمعالجة رسائل المتابعة (`SendAutomationFollowUp`) والتذكيرات (`SendDueReminders`).
   - التأكد من تشغيل عمال الطابور (`php artisan queue:work --stop-when-empty`) بصورة دورية لمعالجة مهام الويب هوك غير المتزامنة.

2. **التدقيق النهائي على لوحة فحص النظام (System Health):**
   - مراجعة صفحة `/admin/system-health` في لوحة Filament للتأكد من خلو النظام من أي مؤشر خطر (`launch_readiness`).
   - التأكد من حجب توثيق Swagger على الإنتاج (`L5_SWAGGER_PUBLIC_IN_PRODUCTION=false`) ومطابقة إعدادات الـ CORS مع دومين الواجهة الأمامية فقط.

3. **تنظيف البيانات التجريبية قبل الإطلاق (Production Data Hygiene):**
   - تنفيذ أمر التنظيف المخصص والمحمي:
     ```bash
     php artisan app:clean-qa-data
     ```
     لحذف حسابات وتجارب الـ QA مع الاحتفاظ التام بحساب الأدمن المعتمد للمالك والعميل.

4. **إجراء الاختبار التجريبي الشامل (End-to-End Live Verification):**
   - بمجرد قبول مراجعة Meta، يتم إجراء دورة اختبار كاملة بحسابات حقيقية:
     - ربط صفحة إنستغرام وتفعيل أتمتة تعليق ورسالة خاصة.
     - ربط صفحة فيسبوك وتجربة المراسلة وصندوق الوارد.
     - تجربة تدفق الاشتراك والدفع ببطاقة تجريبية حقيقية.
