# مهام الفرونت: مطابقة الـ API

- **الفرونت:** `blexchat-website` فرع `main` (`fdf04b1`).
- **الباك إند:** `master` (`d6e4f2e`).
- كل مهمة فيها: **وين بالفرونت** و**شو بيبعت الباك إند**.
- الأولوية: 🔴 خطأ ظاهر للمستخدم · 🟠 ناقص أو غلط بالداتا · 🟢 ميزة جاهزة بالباك إند وما مستعملة.

**الخلاصة:** كل الطلبات اللي بيعملها الفرونت موجودة بالباك إند، بنفس الـ method والـ parameters. المهام تحت هي أخطاء قراءة، وحقول ما بتنقرأ، وميزات جاهزة وما مربوطة.

---

## 🔴 أخطاء ظاهرة للمستخدم

### 1. سطر التحويل بالمحادثة بيطلع فيه كود
- **وين:** `src/app/liveInbox.ts:88` و`:93`، بـ `toMessage`.
- **المشكلة:** بياخد النص من `content`، وفيه كود السبب (مثلاً `unmatched`).
- **الباك إند:** `GET /api/v1/conversations/{id}/messages`. رسالة `type: 'handover'` فيها `reason`، وهو النص الجاهز بلغة الطلب.
- **التصليح:**
  ```ts
  if (m.type === 'handover') return { id, from: 'handover', reason: first(m.reason, m.content) ?? '', time }
  ```

### 2. حالة "automation" بالمحادثة ما بتنقرأ
- **وين:** `src/app/liveInbox.ts:72–81`، بـ `toConversation`.
- **المشكلة:** `auto` بيعتمد على `automation_id` و`last.is_automated`، وهدول مش موجودين بالرد. فالمحادثة اللي عم ترد عليها الأتمتة بتطلع `open`.
- **الباك إند:** `GET /api/v1/conversations`، والحقل `state` قيمه `human` أو `automation` أو `open`.
- **التصليح:**
  ```ts
  state: c.state === 'human' || c.state === 'automation' || c.state === 'open' ? c.state : (human ? 'human' : auto ? 'automation' : 'open'),
  ```

### 3. سؤال الإيميل مفتوح لباقة Essential، والباك إند بيرفضه
- **وين:**
  - `src/pages/wizard/useUnlocked.ts:6`: `WIZARD_PLAN = 'essential'`.
  - `src/app/liveAutomations.ts:103`: `ask_email: paid && …`.
- **المشكلة:** مستخدم Essential بيفعّل "اسأل عن الإيميل"، وبيرجعله `422` بالكود `email_capture_extra`.
- **الباك إند:** `GET /api/v1/plans` → `has_email_capture` لكل باقة. بيكون `true` من Pro وطالع.
- **التصليح:** افتحوا `ask_email` بس إذا باقة المستخدم `has_email_capture === true`. وزر الترقية خلّوه يروح على أول باقة فيها `has_email_capture`.

### 4. بطاقة الـ leads مقفولة بقاعدة ثابتة
- **وين:** `src/pages/app/Home.tsx:108`: `leadsLocked = !covers('pro')`.
- **الباك إند:** `GET /api/v1/dashboard` → `last_seven.metrics[]`، والعنصر اللي `id: 'leads'` فيه `locked` (`true` إذا الباقة ما فيها جمع إيميل). وبـ `/plans` في `has_email_capture`.
- **التصليح:** اقرأوا `locked` من الرد بدل `covers('pro')`.

### 5. `@` قدام الاسم
- **وين:**
  - `src/app/liveInbox.ts:53`
  - `src/app/liveContacts.ts:27`
- **المشكلة:** `handle` بيرجع اليوزرنيم، وإذا ما في يوزرنيم بيرجع الرقم، وإذا ما في رقم بيرجع الاسم. الفرونت بيحط `@` قدام أي شي ما بيبدأ بـ `+`، فممكن يطلع "@Reem Khalid".
- **الباك إند:** `contact.handle` و`contact.name`، بـ `/conversations` و`/contacts`.
- **التصليح:** حطوا `@` بس إذا `handle` مش رقم (ما بيبدأ بـ `+`) ومش نفس `name`. غير هيك اعرضوا `handle` متل ما هو.

### 6. المنشورات: "ما في منشورات" بدل "عم نجيبها"
- **وين:** `src/app/liveAccount.ts:287–300`، بـ `loadPosts`.
- **المشكلة:** ما بيقرأ `meta.synced`. أول مرة، قبل ما تخلص المزامنة، بتطلع القائمة فاضية وكأنه ما في منشورات.
- **الباك إند:** `GET /api/v1/channels/{id}/posts` → `meta.synced` (`false` يعني المزامنة لسا شغالة).
- **التصليح:** إذا `synced === false`، اعرضوا حالة تحميل، وأعيدوا الطلب بعد شوي.

---

## 🟠 داتا ناقصة أو غلط

### 7. مجلد الأتمتة ثابت
- **وين:** `src/app/liveAutomations.ts:140`: `folder: 'instagram'`.
- **الباك إند:**
  - كل أتمتة فيها `automation_folder_id`.
  - `GET /api/v1/automation-folders` بيرجع `id` و`name` و`automations_count`.
- **التصليح:** اربطوا `folder` بـ `automation_folder_id`، وجيبوا أسماء المجلدات من `/automation-folders`.

### 8. الـ CTR صار `0` بدل `null`
- **وين:** عرض الـ CTR بقائمة الأتمتات وصفحة التفاصيل.
- **الباك إند:** `GET /api/v1/automations` → `ctr` صار `0` إذا ما في ولا رسالة فيها زر.
- **التصليح:** إذا بدكم تضل "n/a" تطلع، اعرضوها لما `runs === 0`، أو لما الأتمتة ما فيها زر.

### 9. خطوات شاشة الربط بتنحذف إذا مش 3
- **وين:** `src/pages/auth/liveOnboarding.tsx:46–47`: `if (steps.length === 3)`.
- **الباك إند:** `GET /api/v1/onboarding/channels/{channel}/connect` → `steps[]`.
- **التصليح:** اقبلوا أي عدد من الخطوات.

### 10. شاشة الربط: روابط وحقول ما بتنقرأ
- **وين:**
  - `src/pages/auth/liveOnboarding.tsx:48–51`، بـ `copyFrom`.
  - `src/app/onboardingApi.ts:53–56`.
- **الحقول المتجاهَلة:**
  - `options[].url`
  - `ineligible.help_url`
  - `can`: هل الربط متاح هلق.
  - `waiting_in`: إذا الربط لسا مش متاح.
- **التصليح:** اعرضوا `url` و`help_url` كروابط. استعملوا `can` لتعطيل الزر، و`waiting_in` لنص الانتظار.

### 11. ميزات الباقة بتنحذف إذا مش 3
- **وين:** `src/content/pagesApi.ts:185`: `p.features.length === 3`.
- **الباك إند:** `GET /api/v1/plans` → `features[]`.
- **التصليح:** اعرضوا القائمة كلها، أي عدد كان.

### 12. أرقام التوفير ما بتنقرأ
- **وين:** صفحة الأسعار وشاشة الدفع (`src/app/liveCheckout.ts`).
- **الباك إند:**
  - `annual_saving_up_to` بـ `meta` تبع `/plans`، وبـ `/checkout`.
  - `coupons_on_trial` بـ `/checkout`: هل الكوبون بيشتغل على الفترة التجريبية.
- **التصليح:** اقرأوهن بدل الأرقام الثابتة. واخفوا حقل الكوبون إذا `coupons_on_trial === false` والمستخدم بالتجربة.

### 13. تعديل جهة الاتصال: بس الـ tags
- **وين:** `src/app/liveContacts.ts:82`: `PATCH` مع `{ tags }` بس.
- **الباك إند:** `PATCH /api/v1/contacts/{id}` بيقبل `name` و`phone` و`email` و`city` و`gender` و`tags`.
- **التصليح:** إذا بدكم المستخدم يعدّل بيانات جهة الاتصال، ابعتوا هالحقول كمان.

### 14. رسالة المتابعة بتنبعت بس إذا في رابط
- **وين:** `src/app/liveAutomations.ts:104`: `follow_up: paid && !!d.followUp && !!d.link`.
- **الباك إند:** `POST /api/v1/automations/wizard` بيقبل `follow_up` بدون رابط.
- **التصليح:** شيلوا شرط `!!d.link`، إلا إذا هاد قرار تصميم مقصود.

### 15. تفاصيل الأتمتة: المنشور من مكان تاني
- **وين:** `src/pages/app/AutomationDetail.tsx:135`: `usePosts()`.
- **الباك إند:** `GET /api/v1/automations/{id}` → `post`، المنشور اللي بتراقبه الأتمتة، جاهز مع الرد.
- **التصليح:** اعرضوا `post` من رد الأتمتة، فما بتحتاجوا تجيبوا كل المنشورات.

### 16. ~~تاريخ الحذف بالـ Trash~~ ✔ منتهية
- `src/pages/app/Trash.tsx:36` بيقرأ `purges_at`، وبيعرض "Deletes in". ما في شي لازم يتعمل.

---

## 🟢 جاهز بالباك إند وما مستعمل

| # | الميزة | الـ Endpoint / الحقل | وين بالفرونت |
|---|---|---|---|
| 17 | زر Blexchat AI | `PATCH /api/v1/automations/{id}/ai` مع `{ enabled }`، والحقل `ai_enabled`. بيرجع `403 plan_required` إذا الباقة ما فيها AI | `src/pages/app/ai/` (AiLanding) ما بيطلبه |
| 18 | المفضلة والـ labels | `PATCH /api/v1/conversations/{id}` مع `starred` و`labels[]`، والحقلين بيرجعوا بكل محادثة | `src/app/liveInbox.ts` بيبعت `read` بس (`:142`) |
| 19 | فلاتر المحادثات | `?folder=favorites\|labels` و`?label=` و`?state=human` (Needs you) و`?sort=oldest` | `src/app/liveInbox.ts` |
| 20 | المنشور مع التعليق | رسالة `type: 'comment'` فيها `post` (الصورة والنص) | `toMessage` بـ `src/app/liveInbox.ts:87` |
| 21 | صفحات الخصوصية والشروط | `GET /api/v1/legal/privacy` و`GET /api/v1/legal/terms` (Markdown) | ما في صفحات إلهن |
| 22 | قائمة الموقع والفوتر | `GET /api/v1/home` → `navigation` (`items`، `menus`) و`footer` (`cta`، `columns[].links`) | `src/content/homeApi.ts` و`apiToLocale.ts` ما بيقرأوهن، والقوائم ثابتة بالكود |

### حقول بترجع وما بتنقرأ (اختياري)

| الـ Endpoint | الحقول |
|---|---|
| `GET /api/v1/dashboard` | `user` و`channels` و`automations` و`last_seven`. بينقرأ بس `checklist` و`start_here` |
| `GET /api/v1/onboarding` | `channels` و`survey_completed_at` و`subscription` |
| `GET /api/v1/channels` و`/channels/{id}` | `followers` و`connected_at` و`token_expires_at`، و`status: 'expired'` بينقرأ "مش شغالة" بس، بدون زر إعادة ربط |
| `GET /api/v1/channels/{id}/posts` | `media_type` و`posted_at`. وكمان `per_page` أقصاه 100، فلازم pagination إذا المنشورات أكتر |

---

## ✅ متطابق، ما في شي

- تسجيل الدخول (الإيميل وGoogle)، و`me`، والخروج، ومعهم أكواد الأخطاء: `link_expired` و`google_failed` و`resend_too_soon` و`too_many_attempts`.
- الـ onboarding: `next_step`، والـ survey، والـ first moves، وشاشة القناة بعد الربط.
- الباقات، والدفع (`url` و`status`)، والكوبون (`percent_off` و`discount` و`price` و`billed_total`)، والاشتراك (`trial_days_left` و`status`)، وإلغاء الباقة.
- إضافة جهة اتصال: `first_name` و`last_name` و`dial_code` و`phone` و`email` و`gender` و`consent`. متطابقة مع `StoreContactRequest`.
- عدّاد جهات الاتصال، وإعدادات الـ inbox، والقوالب.
- الإعدادات: `billing_period` و`paid_by_card` و`current_period_end`.
- الصفحة الرئيسية (`sections` و`demo_replies`)، والأسعار (`copy` و`max_saving` و`compare` و`faq`)، والحلول، وشاشات الدخول.
- خطوة test بالـ checklist: `Home.tsx:96` صار يطلب `loadDashboard()`. ✔ انحلّت.

---

## على السيرفر (مش للفرونت)

- `APP_DEBUG` لازم ينطفي قبل الإطلاق.
- لازم يكون في queue worker شغّال، لأن رسائل المتابعة (`follow_up`) بتعتمد عليه.
