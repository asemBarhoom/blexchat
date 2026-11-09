# مطابقة تصميم فيغما مع الـ API

- **فيغما:** [blexchat (Copy)](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-)، صفحة **App** (عدد الشاشات 120 تقريباً).
- **الباك إند:** `master`. **الفرونت:** `main` (`fdf04b1`).
- كل بند فيه رابط الشاشة بفيغما (`node-id`)، والـ endpoint، ووين التصليح.

**آخر تحديث:** 9 نوفمبر 2026. عطل payload الخاص بإرسال Instagram أُصلح في `d46be57`، وأصبحت أتمتة القنوات الوهمية وتعليقات Messenger/TikTok وتدفقات OAuth الوهمية موثقة ومختبرة. الباقي شغل فرونت أو تكاملات provider خارجية.

**الخلاصة:** كل اللي بالتصميم صار إله API. الـ 5 أشياء الأصلية الناقصة بالباك إند انبنت (B1–B5)، والباقي شغل فرونت. وأغلب شغل الفرونت موجود أصلاً بملف `FRONTEND-API-TASKS.md`.

---

## 🔧 كان ناقص بالباك إند: ✔ انبنى (فرع `feat/figma-backend-gaps`)

| # | الشاشة بفيغما | شو انضاف بالـ API |
|---|---|---|
| B1 | [W15 · failed-channel](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-10433) | `POST /automations/wizard` و`PATCH /automations/{id}/toggle` (لما تشغّل أتمتة موقفة) بيرجعوا `422`، مع `{ code: 'channel_disconnected', channel: 'instagram', message }` إذا القناة مش `active` |
| B2 | [W15 · failed-limit](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-10523) | حقل جديد بالباقة: `live_automations_limit` (Free = 3، والباقي `null` يعني بلا حد). بيرجع بـ `GET /plans`، والأدمن بيعدّله من Filament. نفس الـ endpoints بيرجعوا `422` مع `{ code: 'live_limit', limit: 3, message }` |
| B3 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | `PATCH /conversations/{id}` مع `{ remind_at: ISO }` (بالمستقبل، و`null` بيمسحه). على Free بيرجع `403` مع `code: plan_required`. وفي `GET /conversations?folder=reminders` (الأقرب أول)، والحقل `remind_at` بكل محادثة |
| B4 | [Automation detail · preview](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-5759) | المنشور صار فيه `comments` جنب `likes`، من `comments_count` تبع Instagram |
| B5 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | `GET /conversations` صار يرجع `meta.total`، عدد كل المحادثات اللي بتطابق الفلاتر، مش بس الصفحة |

**ملاحظة على B3:** التذكير هلق بيحفظ الوقت وبيعرض المحادثة بمجلد Reminders. ما في إشعار بينبعت لما يجي الوقت. هاد بدو قرار: إيميل، ولا push، ولا بس علامة بالـ inbox.

**اختياري:**
- [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564) فيها "Search automations" و"3 live of 5". الفرونت بيقدر يعملهن من القائمة، لأنه بيطلبها كلها (`per_page=100`). بس إذا الأتمتات كترت، بدها `?search=` و`meta.live`.
- [Contacts · Import](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4242): التصميم نفسه بيقول "Import is part of the full build"، فمش مطلوب هلق.
- [Blexchat AI](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6308): "Try it" مكتوب عليه إنه demo. ما في AI حقيقي بالباك إند، و`ai_enabled` مجرد switch.

---

## 💻 شغل الفرونت: التصميم والـ API جاهزين، والفرونت ما بيقرأ

| # | الشاشة بفيغما | شو بالتصميم | الـ API | المهمة بـ `FRONTEND-API-TASKS.md` |
|---|---|---|---|---|
| F1 | [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564) | تبويبات All / Instagram / Seasonal و"+Create folder" | `/automation-folders` و`automation_folder_id` | #7 |
| F2 | [Automations](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4564) | الأتمتة اللي ما إلها داتا بيطلعلها "n/a" و"—" | `runs: 0` و`ctr: 0` | #8: إذا `runs === 0` اعرضوا "—" |
| F3 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | Labels وFavorites وUnread وNewest▾ وAll channels▾ | `?folder=` و`?label=` و`?unread=1` و`?sort=oldest` و`?channel_id=`، و`PATCH starred/labels` | #18 و#19 |
| F4 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | علامة "Needs you" على المحادثة | `state: 'human'` | #2 |
| F5 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) و[Contact detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-4433) | "Handed over to you" ومعه السبب | `message.reason` | #1 |
| F6 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | "Started by … on your post"، والمنشور مع التعليق | `post` برسالة التعليق | #20 |
| F7 | [Home](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-2212) و[Automation detail](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-5651) | "Leads collected" و"Emails" مقفولين ("Not included") | `last_seven.metrics[leads].locked`، و`has_email_capture` | #3 و#4 |
| F8 | [W06 · ineligible](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-6286) | رابط "How to switch to a professional account" | `ineligible.help_url` | #10 |
| F9 | [W11 · noposts](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-7481) و[W11 · loading](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-7411) | حالة "ما في منشورات" منفصلة عن حالة التحميل | `meta.synced` | #6 |
| F10 | [Automation detail · preview](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-5759) | المنشور مع عدد الإعجابات (١٬٢٠٤) | `post` و`post.likes` بـ `GET /automations/{id}` | #15 |
| F11 | [W15 · failed-channel](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-10433) و[W15 · failed-limit](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=49-10523) | رسالة الخطأ تحت "Go live" | `saveWizard` بـ `src/app/liveAutomations.ts:202`: اقرأوا `error.body.code`، وخدوا العدد من `limit` بدل `FREE_AUTOMATIONS = 3` بـ `ReadyToLive.tsx:34` | جديدة |
| F12 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | "All chats 6" | `meta.total` | جديدة |
| F13 | [Inbox](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-6087) | مجلد Reminders (مقفول هلق بـ `Inbox.tsx:40`) | `?folder=reminders`، و`PATCH remind_at` | جديدة |
| F14 | [Automation detail · preview](https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-?node-id=55-5759) | "عرض كل التعليقات (٤)" | `post.comments` | جديدة |

---

## 🎨 داتا العرض صارت من فيغما (فرع `chore/demo-seed-from-figma`)

حساب Nour التجريبي صار يرجّع نفس اللي بالتصميم:

| وين | قبل (من الفرونت) | هلق (من فيغما) |
|---|---|---|
| المنتج | جاكيت الشتاء، 65 دولار، `winter-jacket` | **عباية الشتاء، ٢٤٩ ريال**، `nourboutique.com/abaya-winter` |
| الرسالة الخاصة | "أهلًا وسهلًا! 👋 هذا جاكيت الشتاء…"، والزر "شاهدي المنتج" | "هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال…"، والزر **"شوفي المنتج"** |
| المنشور | "جاكيت الشتاء وصل…" | "عباية الشتاء وصلت ✨ اكتبي «السعر» بالتعليقات…"، ومعه ١٬٢٠٤ إعجاب و**٤ تعليقات** |
| التعليقات | فصحى | خليجي: "السعر لو سمحتي؟ 😍"، "بكم هذي؟"، "تجنن ما شاء الله 💜"، "أبي أطلب، وين الرابط؟" |
| جهات الاتصال | Hala Q.، Mona F.، ومدن الشام ومصر | **Hessa Q.** و**أم فيصل**، ومدن الخليج: Jeddah وRiyadh وDammam وKuwait City وDubai. الـ tags متل فيغما: Winter abaya وAsked price وReady to order وCustomer وRepeat buyer وFan |
| محادثة Reem | "مقاس L… القاهرة" | "طيب عندكم مقاس ٥٨؟ وأبي التوصيل لجدة قبل الخميس 🙏"، وإيميلها **ما انجمع** (`null`) متل فيغما |
| آخر رسالة بالـ inbox | فصحى | متل فيغما: "وصلني الطلب اليوم، تسلمون! 🙏"، "تجنن ما شاء الله 💜"، "كم سعر الطرحة الشيفون؟" |
| الـ First moves (W10) | `winter-jacket`، "my city"، "$50" | `nourboutique.com/abaya`، "Do you deliver to Riyadh?"، "free over 200 SAR" |
| قالب "Auto-DM links from comments" | نص الجاكيت | نص العباية والزر "شوفي المنتج" |

**لتطبيقها على السيرفر:** شغّل الـ seeders وحدة وحدة، مش `db:seed` كامل:
`PostSeeder` ثم `AutomationTemplateSeeder` ثم `FirstMoveSeeder` ثم `AutomationSeeder` ثم `CommentSeeder` ثم `ConversationSeeder` ثم `ContactSeeder` ثم `AutomationHistorySeeder`.

### ⚠️ أشياء بفيغما ما بتتطابق، لأن التصميم نفسه متناقض

| بفيغما | ليش ما طابقتها |
|---|---|
| Runs بقائمة الأتمتات: 412 و1,284 و3,907 | هدول بدهم آلاف المحادثات وجهات الاتصال، والتصميم نفسه بيقول "6 contacts" على Free. الـ **CTR** متطابق: 38% و22% و41%، بس الـ Runs من تاريخ صغير حقيقي (13 و9 و34) |
| "Comment PRICE" بيطلع n/a | نفس الأتمتة ردّت على Reem وSara وHessa بالـ inbox، فإلها runs حقيقية |
| "6 contacts" و"6/25" | العدد 24، لأنه في 18 زبون قديم بالتاريخ اللي اخترته قبل، لحتى تطلع الأرقام حقيقية |
| "1 connected channel" | القنوات 3، لأنه بجهات الاتصال بالتصميم نفسه في WhatsApp وTikTok |
| مصدر Reem: "Auto-DM links from comments" | هاد اسم الـ first move، مش أتمتة بالقائمة. المصدر عنا هو الأتمتة اللي ردّت عليها فعلاً |
| سبب التحويل: "Asked about a size and a delivery date — outside the automation." | الـ API بيبعت سبب عام حسب الكود: "Asked something the automation does not answer." نص مخصص لكل محادثة بده AI |
| أوقات التعليقات بـ W12 (٢د، ٥د، ٩د، ١٢د) | بتختلف عن أوقات الـ inbox بنفس التصميم (5 دقايق، 14، 41، 3 ساعات). مشيت على الـ inbox |
| آخر 7 أيام: "61"، "6h 20m"، "148 replies" | أرقام محسوبة من الرسائل الحقيقية، مش نص ثابت |

---

## ✅ متطابق

- **W01–W04** (الدخول والتسجيل): الحالات empty وerror وGoogle failed وcountdown وlimited وexpired، كلها إلها أكواد بالـ API.
- **W05–W07** (القنوات): `/onboarding/channels` و`/connect` و`/channels/{id}`. ما عدا ربط WhatsApp وTikTok وMessenger، لأنه ما إلهن ربط حقيقي بالباك إند.
- **W08–W10**: الـ survey بخطواته الأربع، والباقات بالشهري والسنوي، والـ first move.
- **W11–W15** (الـ wizard): الـ trigger والكلمات والرد والرسالة، والإضافات المدفوعة (follow وemail وfollowup). كلها بـ `POST /automations/wizard`.
- **Home**: التحية والقنوات (`dashboard.user` و`channels`)، والـ checklist، والـ start here، وآخر 7 أيام. وحالات البانر: التجربة بـ `subscription.trial_days_left`، والحد بـ `contacts/usage.over_limit`، والقناة المفصولة بـ `channel.status`.
- **Checkout**: confirming وdeclined وstarting trial.
- **Contacts و New contact و Contact detail**: كل الحقول موجودة، ومنهن `language` و`subscribed` و`conversation` و`first_seen_at` و`source`.
- **Trash**: "Deletes in 28 days" من `purges_at`. الفرونت بيقرأه (`Trash.tsx:36`).
- **Flow**: بيتعبّى من `wizard` و`actions` تبع الأتمتة.
- **Inbox**: "Accept new conversations" من `/inbox/settings`، و"Mark done" من `PATCH status: resolved`.
