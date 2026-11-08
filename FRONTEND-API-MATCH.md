# مطابقة الـ API مع الفرونت

- **الفرونت:** `blexchat-website` فرع `origin/feat/api`، الـ commit `fb440eb`.
- **الباك إند:** `master`، الـ commit `d6e4f2e`.

**الخلاصة:** كل الطلبات اللي بيطلبها الفرونت (49) موجودة بالباك إند، بنفس الـ method ونفس الـ parameters. والحقول متطابقة، إلا 3 أخطاء بالفرونت وكم ملاحظة تحت.

---

## ✅ متطابق

- تسجيل الدخول: الإيميل وGoogle و`me` وتعديل البروفايل والخروج.
- الـ onboarding: الـ survey والقنوات والباقة والـ first moves.
- الباقات والدفع والكوبونات والاشتراك، ومعهم إلغاء الباقة.
- القنوات، وفصل القناة، والمنشورات، والقوالب.
- الأتمتات: القائمة والـ Trash والـ wizard والإحصائيات والتشغيل والإيقاف.
- جهات الاتصال وعدّاد جهات الاتصال.
- صفحات الموقع: الصفحة الرئيسية والأسعار والحلول وشاشات الدخول.

---

## ⚠️ تصليحات بالفرونت

الباك إند بيبعت الصح بهالحالات، والتصليح من جهة الفرونت.

### 1. سبب التحويل بالمحادثة
- **المشكلة:** `toMessage` بـ `src/app/liveInbox.ts` بياخد نص سطر التحويل من `content`، وفيه كود السبب (`unmatched`).
- **التصليح:** اعرضوا `message.reason`، لأنه النص الجاهز والمترجم بلغة الطلب.

```ts
if (m.type === 'handover') return { id, from: 'handover', reason: first(m.reason, m.content) ?? '', time }
```

### 2. حالة "automation" بالمحادثة
- **المشكلة:** `toConversation` بيقرأ `state === 'human'` بس. فالمحادثة اللي عم ترد عليها الأتمتة بتطلع `open`.
- **التصليح:** اقرأوا `state` مباشرة:

```ts
state: c.state === 'human' || c.state === 'automation' || c.state === 'open' ? c.state : (human ? 'human' : auto ? 'automation' : 'open'),
```

### 3. خطوة test بالـ checklist
- **المشكلة:** `loadAccount()` بـ `src/app/liveAccount.ts` بيحفظ أول تحميل بـ `started ??=`، فـ `/dashboard` ما بينطلب مرة تانية إلا بـ reload كامل للصفحة.
- **التصليح:** صدّروا `loadDashboard` واطلبوها لما تفتحوا الصفحة الرئيسية، أو بعد أي test-events.

---

## ملاحظات

| الحقل | شو تغيّر | الأثر على الفرونت |
|---|---|---|
| `automations[].ctr` | صار `0` بدل `null` إذا ولا رسالة فيها زر | بيطلع "0%" بدل "n/a" |
| `conversation.contact.id` | صار دايماً موجود، وكل محادثة إلها جهة اتصال | رابط "View contact" دايماً شغّال |
| `contact.handle` | إذا ما في يوزرنيم بيرجع الرقم، وإذا ما في رقم بيرجع الاسم | الفرونت بيحط `@` قدامه، فممكن يطلع "@Reem Khalid" إذا الشخص ما إله يوزرنيم ولا رقم |
| `automation_folder_id` | موجود بكل أتمتة | الفرونت كاتب `folder: 'instagram'` ثابت |

---

## جاهز بالباك إند وما مستعمل بالفرونت

| الميزة | الـ Endpoint / الحقل |
|---|---|
| مجلدات الأتمتة | `GET/POST/PATCH/DELETE /api/v1/automation-folders`، و`automation_folder_id` بـ `PATCH /automations/{id}`، و`?folder_id=` بالقائمة |
| زر Blexchat AI | `PATCH /api/v1/automations/{id}/ai` مع `{ enabled }`، وحقل `ai_enabled`. بيرجع 403 `plan_required` إذا الباقة ما فيها AI |
| المفضلة والـ labels بالمحادثات | `PATCH /api/v1/conversations/{id}` مع `starred` و`labels` |
| فلاتر المحادثات | `?folder=favorites\|labels` و`?label=` و`?state=human` (Needs you) و`?sort=oldest` |
| جمع الإيميل | `has_email_capture` بـ `GET /plans`، والـ dashboard بيبعت `locked` لبطاقة الـ leads |
| صفحات الخصوصية والشروط | `GET /api/v1/legal/privacy` و`GET /api/v1/legal/terms` (Markdown) |
| المنشور مع التعليق | رسالة التعليق بالـ thread فيها `via: 'comment'` و`post` |
| أرقام الأتمتات | `runs` و`ctr` بـ `GET /automations` و`GET /automations/{id}`، بدون ما تطلبوا stats لكل وحدة |

---

## على السيرفر

- `APP_DEBUG` لسا شغّال، ولازم ينطفي قبل الإطلاق.
- لازم يكون في queue worker شغّال، لأن رسائل المتابعة (`follow_up`) بتعتمد عليه.
