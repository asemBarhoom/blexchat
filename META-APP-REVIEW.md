# Meta App Review: Blexchat

The app is **Blexchat**, App ID `1396711619210277`.

This file lists what we need to submit so that any business can connect Instagram and Messenger, not just testers.
The steps are in Arabic. The texts to paste into Meta are in English, because Meta's reviewers read English.

---

## 0. قرارات لازم تنحسم قبل أي إشي

1. **مين صاحب التطبيق؟**
   - التطبيق هلأ بـ Business Portfolio اسمه «ASEM».
   - توثيق الشركة (Business Verification) بينعمل للـ Portfolio اللي بيملك التطبيق، وبدها أوراق رسمية باسم الشركة.
   - إذا التطبيق رح يكون ملك العميل، الأفضل ننقله لـ Portfolio العميل **قبل** التوثيق والمراجعة.
2. **Tech Provider:**
   - Meta بتطلب نأكد إنه التطبيق بيخدم بزنسات تانية. هاد وضع Blexchat، لأن كل عميل بيربط حسابه.
   - هالخيار **ما بيرجع بعد ما ينختار**. مكانه: Dashboard ← Become a Tech Provider ← Yes, I'm a Tech Provider.
   - ما كبسته. إنت أو العميل بتكبسوه بعد ما يتحسم البند 1.

## 1. قبل التقديم (لازم كله يكون جاهز)

- [ ] `META_APP_SECRET` و`INSTAGRAM_APP_SECRET` صح على Cloud (32 خانة)، وبعدها Deploy.
- [ ] Business Verification للـ Portfolio: سجل تجاري، أو فاتورة رسمية، أو كشف بنك باسم الشركة وعنوانها، ودومين أو إيميل باسم الشركة.
- [ ] App settings ← Basic:
  - Privacy Policy: `https://blexchat-website.vercel.app/legal/privacy`
  - Terms: `https://blexchat-website.vercel.app/legal/terms`
  - Data deletion callback: معبّى ✅
  - App icon بحجم 1024×1024: **ناقص**
  - Category: **فاضي**، اختار «Business and pages»
  - Contact email
- [ ] سياسة الخصوصية لازم تذكر: Instagram وFacebook وشو البيانات اللي بناخدها (الرسائل، التعليقات، اسم الحساب)، واستخدام الذكاء الاصطناعي، وطريقة حذف البيانات. هاد ضمن R-14، والعميل لازم يعتمده.
- [ ] الأفضل يكون الموقع على دومين نهائي قبل المراجعة. ‎`vercel.app` بيمشي، بس Meta أحياناً بتعلّق عليه.
- [ ] حساب تجريبي للمراجِع:
  - إيميل وكلمة سر لحساب Blexchat. بيعملهم عاصم، **وما بينكتبوا بهالملف**.
  - مع حساب إنستغرام Business وصفحة فيسبوك تجريبية، مربوطين ببعض.

## 2. الصلاحيات المطلوبة (Advanced Access)

### إنستغرام (Instagram API with Facebook Login، لأنه الكود شغّال على Unified Login)

الصلاحيات المطلوبة هي اللي بالـ Configuration تبع `INSTAGRAM_CONFIG_ID`. الأغلب:

| Permission | ليش بنحتاجها |
|---|---|
| `instagram_basic` | اسم الحساب والصورة والمنشورات، عشان المستخدم يختار المنشور للأتمتة |
| `instagram_manage_comments` | نقرأ التعليقات على منشورات العميل ونرد عليها تلقائياً |
| `instagram_manage_messages` | نبعت رسالة خاصة لصاحب التعليق، ونرد على الرسائل بصندوق الرسائل |
| `pages_show_list` | نجيب صفحة فيسبوك المربوطة بحساب إنستغرام |
| `pages_read_engagement` | مطلوبة مع الصلاحيات اللي فوق للوصول لحساب إنستغرام عن طريق الصفحة |
| `business_management` | للوصول لحسابات إنستغرام المخصصة للمستخدم بالـ Portfolio (`/me/assigned_instagram_accounts`) |

### ماسنجر (صفحة فيسبوك)

الكود بيطلب هلأ: `pages_show_list,pages_manage_metadata,pages_messaging`.

| Permission | ليش |
|---|---|
| `pages_messaging` | الرد على رسائل الصفحة تلقائياً ومن صندوق الرسائل |
| `pages_manage_metadata` | نشترك بـ Webhooks الصفحة (messages وfeed) |
| `pages_show_list` | المستخدم يختار أي صفحة بدو يربطها |
| `pages_read_engagement` | نقرأ تعليقات منشورات الصفحة |
| `pages_manage_engagement` | نرد على تعليقات الصفحة |

> ⚠️ **ملاحظة للباك:** الرد على تعليقات صفحة فيسبوك بدو `pages_read_engagement` و`pages_manage_engagement`، بس الكود ما بيطلبهم بالـ scope (`ChannelOAuthProvider.php:25`). لازم ينضافوا قبل التقديم، وإلا الرد على التعليقات رح يفشل لما التطبيق يصير Live. لو بدنا نقدّم الرسائل بس، بنشيل هالجزئية من الفيديو.

واتساب مش ضمن هالجولة، وإله ملف لحاله (R-5).

## 3. النصوص اللي بتنحط بالـ App Review (بالإنجليزي)

### App description (general)

> Blexchat is a customer-messaging platform for small businesses. A business owner connects their own Instagram professional account or Facebook Page, then builds automations: when a customer comments a keyword on a post or sends a message, Blexchat replies publicly to the comment and/or sends the customer a private message with the information they asked for (price, link, booking). Replies the automation cannot answer appear in a shared inbox where the business replies manually. Blexchat only acts on accounts the business owner connected, only in response to a customer's comment or message, and never sends unsolicited messages.

### instagram_basic

> We use instagram_basic to read the connected Instagram professional account's username, profile picture and its recent posts and reels. The business owner sees their posts in Blexchat's "Pick a post" screen and chooses which post an automation listens to. We do not read data from any account other than the one the owner connected.

### instagram_manage_comments

> When a customer comments on the business's post, Instagram sends us the comment via webhook. If the comment matches an automation the business created (for example the keyword "price"), Blexchat posts a short public reply to that comment on the business's behalf (for example "We sent you the details in DM"). Without this permission the business cannot answer comments automatically.

### instagram_manage_messages

> After a customer comments, Blexchat sends one private reply to that customer (Instagram's private-reply feature, within 7 days and once per comment) containing the information requested, such as a product link. Blexchat also shows incoming direct messages in its inbox so the business can answer them, manually or with an automated reply, within the 24-hour messaging window. Messages are sent only in response to a customer's comment or message.

### pages_show_list / pages_read_engagement / business_management

> Instagram professional accounts are connected through their linked Facebook Page. We use pages_show_list and pages_read_engagement to find the Page linked to the account the owner chooses during Facebook Login for Business, and business_management to read the Instagram accounts assigned to the user in their business portfolio. We store only the account ID, username and the access token needed to reply on the owner's behalf.

### pages_messaging

> A business connects its Facebook Page to Blexchat. Messages customers send to the Page appear in Blexchat's inbox. The business replies from Blexchat, or an automation answers common questions (opening hours, prices, links) immediately. Replies are sent only to people who messaged the Page, within Messenger's 24-hour window.

### pages_manage_metadata

> We subscribe the connected Page to the "messages" and "feed" webhook fields so Blexchat receives new messages and comments in real time. Without this, automations and the inbox cannot work.

### pages_manage_engagement (if requested)

> When a customer comments a keyword on the Page's post, Blexchat replies publicly to that comment on the Page's behalf and sends the commenter a private reply with the requested information.

## 4. سيناريو الفيديو (Screencast)

Meta بدها فيديو لكل صلاحية يوضّح الرحلة من أولها لآخرها. بينفع نعمل فيديو واحد طويل ونستخدمه لكل الصلاحيات.

- **واجهة إنجليزية:** نختار English من الموقع.
- **المدة:** 2 لـ 4 دقايق، بدون صوت، بس مع تعليق مكتوب على الشاشة إذا أمكن.

1. صفحة blexchat-website.vercel.app ← **Sign in** ← دخول بحساب المراجِع.
2. **Channels ← Connect Instagram** ← نافذة Facebook Login for Business بتفتح ← نختار حساب إنستغرام ← **Allow** على كل الصلاحيات (لازم تبيّن بالفيديو) ← نرجع لـ Blexchat وبتطلع «Connected».
3. **Automations ← New** ← نختار «Comment on a post» ← **Pick a post** (بيبيّن منشورات الحساب، يعني instagram_basic) ← keyword: `price` ← الرد العام والرسالة الخاصة مع الرابط ← **Publish**.
4. من موبايل تاني بحساب عميل: نعلّق `price` على نفس المنشور.
5. نرجع على إنستغرام: الرد العام ظهر على التعليق (manage_comments)، والعميل وصلته رسالة خاصة (manage_messages).
6. بـ Blexchat: **Inbox**، المحادثة ظاهرة ← نرد يدوياً ← الرد بيوصل على إنستغرام.
7. ماسنجر: **Connect Messenger** ← Facebook Login ← نختار الصفحة ← رسالة من حساب تاني للصفحة ← بتطلع بالـ Inbox ← رد ← بيوصل على ماسنجر.
8. **Settings ← Disconnect**، وصفحة حذف البيانات.

## 5. الترتيب

1. نحسم مين صاحب التطبيق، ونعمل Business Verification.
2. نكبس Tech Provider.
3. نعبّي Basic (الأيقونة والتصنيف)، والباك بيضيف scopes الماسنجر الناقصة.
4. نسجّل الفيديو على production بعد ما يصير الـ App Secret صح، والربط الحقيقي شغّال مع Tester.
5. App Review ← نعبّي أسئلة استخدام البيانات (Data handling) ← نلصق النصوص اللي فوق ← نرفع الفيديو ← Submit.
6. بعد الموافقة: **Publish** للتطبيق (Live mode).

## 6. لحد ما توافق Meta

أي حد بدو يجرب، بنضيفه من **App roles ← Roles ← Add People ← Instagram Tester** بيوزره، وهو بيقبل الدعوة من إنستغرام:
Settings ← Website permissions ← Apps and websites ← Tester invites.
بعدها بيقدر يربط مباشرة، حتى والتطبيق Development.
