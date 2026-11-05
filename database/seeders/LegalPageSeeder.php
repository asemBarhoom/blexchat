<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

/**
 * A starting draft of the Privacy Policy and the Terms of Service, in English and Arabic.
 * Have it reviewed before launch; after that it is edited in the admin panel, so this
 * seeder only creates the pages that do not exist yet and never overwrites an edit.
 */
class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => [$title, $body]) {
            LegalPage::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'effective_on' => '2026-11-01']);
        }
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>}>
     */
    private function pages(): array
    {
        return [
            'privacy' => [
                ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                [
                    'en' => <<<'MD'
                        Blexchat helps businesses answer comments and messages on Instagram, WhatsApp, TikTok and Messenger automatically. This policy explains what we collect, why, and the choices you have.

                        ## What we collect

                        - **Your account:** your name, email address and business name, and your Google profile picture if you sign in with Google.
                        - **Connected channels:** the account name, profile picture and follower count of each channel you connect, and the access the platform grants us to answer on your behalf.
                        - **Conversations:** the comments and messages your channels receive, the replies your automations send, and the contacts they create.
                        - **Billing:** your plan and payment status. Card details are handled by our payment provider and never reach our servers.
                        - **Usage:** which features you use and basic technical data such as your browser and language, to keep the service working.

                        ## How we use it

                        - To run your automations and show your inbox, contacts and stats.
                        - To send you sign-in links and messages about your account.
                        - To keep the service secure and to fix problems.

                        We do not sell your data, and we do not use your customers’ messages to advertise to them.

                        ## Sharing

                        We share data only with the platforms you connect (to send your replies), with the providers that host the service and process payments, and when the law requires it.

                        ## How long we keep it

                        We keep your data while your account is open. An automation you move to the Trash is deleted for good after 30 days. When you close your account, we delete your data within 30 days, except what the law requires us to keep.

                        ## Your choices

                        You can disconnect a channel at any time, edit or delete contacts, and ask us for a copy of your data or to delete your account.

                        ## Changes

                        When this policy changes in a way that matters, we update the date above and tell you by email.
                        MD,
                    'ar' => <<<'MD'
                        يساعد Blexchat الأنشطة التجارية على الرد تلقائيًا على التعليقات والرسائل في Instagram وWhatsApp وTikTok وMessenger. توضّح هذه السياسة ما نجمعه، ولماذا، والخيارات المتاحة لك.

                        ## ما الذي نجمعه

                        - **حسابك:** اسمك وبريدك الإلكتروني واسم نشاطك التجاري، وصورة ملفك في Google إن سجّلت الدخول بها.
                        - **القنوات المربوطة:** اسم الحساب وصورته وعدد متابعيه لكل قناة تربطها، والصلاحية التي تمنحنا إياها المنصة للرد نيابةً عنك.
                        - **المحادثات:** التعليقات والرسائل التي تصل إلى قنواتك، والردود التي ترسلها أتمتتك، وجهات الاتصال التي تُنشئها.
                        - **الفوترة:** خطتك وحالة الدفع. بيانات البطاقة يعالجها مزوّد الدفع ولا تصل إلى خوادمنا أبدًا.
                        - **الاستخدام:** المزايا التي تستخدمها وبيانات تقنية أساسية مثل المتصفح واللغة، لإبقاء الخدمة تعمل.

                        ## كيف نستخدمها

                        - لتشغيل أتمتتك وعرض صندوق الوارد وجهات الاتصال والإحصاءات.
                        - لإرسال روابط تسجيل الدخول والرسائل المتعلقة بحسابك.
                        - لحماية الخدمة وإصلاح المشكلات.

                        لا نبيع بياناتك، ولا نستخدم رسائل عملائك لعرض إعلانات عليهم.

                        ## المشاركة

                        لا نشارك البيانات إلا مع المنصات التي تربطها (لإرسال ردودك)، ومع المزوّدين الذين يستضيفون الخدمة ويعالجون المدفوعات، وعندما يُلزمنا القانون بذلك.

                        ## مدة الاحتفاظ

                        نحتفظ ببياناتك ما دام حسابك مفتوحًا. الأتمتة التي تنقلها إلى سلة المحذوفات تُحذف نهائيًا بعد 30 يومًا. وعند إغلاق حسابك نحذف بياناتك خلال 30 يومًا، إلا ما يُلزمنا القانون بالاحتفاظ به.

                        ## خياراتك

                        يمكنك فصل أي قناة في أي وقت، وتعديل جهات الاتصال أو حذفها، وطلب نسخة من بياناتك أو حذف حسابك.

                        ## التغييرات

                        عندما تتغير هذه السياسة تغييرًا مهمًا، نحدّث التاريخ أعلاه ونخبرك بالبريد الإلكتروني.
                        MD,
                ],
            ],
            'terms' => [
                ['en' => 'Terms of Service', 'ar' => 'شروط الخدمة'],
                [
                    'en' => <<<'MD'
                        These terms apply when you create a Blexchat account or use the service. By continuing, you agree to them.

                        ## Your account

                        - You need a valid email address to sign up, and you are responsible for what happens in your account.
                        - Connect only channels you own or are allowed to manage.

                        ## Using Blexchat

                        - Follow the rules of each platform you connect, including Meta’s and TikTok’s policies on automated messages.
                        - Don’t use Blexchat to send spam, to mislead people, or to send anything unlawful.
                        - You are responsible for the replies your automations send and for the links they share.

                        ## Plans and payment

                        - The Free plan costs nothing. Paid plans are billed monthly or yearly, in advance, and renew until you cancel.
                        - When you cancel, your plan stays active until the end of the period you paid for, then your account moves to Free.
                        - Prices can change; we tell you before a change applies to you.

                        ## The platforms

                        Instagram, WhatsApp, Messenger and TikTok belong to their owners. They can change or limit what Blexchat is able to do, and some features depend on their approval.

                        ## Availability

                        We work to keep Blexchat running, but we can’t promise it will never be interrupted. We are not liable for indirect losses, such as lost sales, caused by an interruption.

                        ## Ending

                        You can close your account at any time. We may suspend an account that breaks these terms or a platform’s rules.

                        ## Changes

                        When these terms change in a way that matters, we update the date above and tell you by email before the change applies.
                        MD,
                    'ar' => <<<'MD'
                        تسري هذه الشروط عند إنشاء حساب في Blexchat أو استخدام الخدمة. وبمتابعتك فإنك توافق عليها.

                        ## حسابك

                        - تحتاج إلى بريد إلكتروني صالح للتسجيل، وأنت مسؤول عمّا يحدث في حسابك.
                        - لا تربط إلا القنوات التي تملكها أو المسموح لك بإدارتها.

                        ## استخدام Blexchat

                        - التزم بقواعد كل منصة تربطها، ومنها سياسات Meta وTikTok بشأن الرسائل الآلية.
                        - لا تستخدم Blexchat لإرسال رسائل مزعجة أو مضلِّلة أو أي محتوى مخالف للقانون.
                        - أنت مسؤول عن الردود التي ترسلها أتمتتك وعن الروابط التي تشاركها.

                        ## الخطط والدفع

                        - الخطة المجانية بلا تكلفة. أما الخطط المدفوعة فتُدفع مقدّمًا شهريًا أو سنويًا، وتتجدد حتى تلغيها.
                        - عند الإلغاء تبقى خطتك فعّالة حتى نهاية الفترة المدفوعة، ثم ينتقل حسابك إلى الخطة المجانية.
                        - قد تتغير الأسعار، وسنخبرك قبل أن يسري أي تغيير عليك.

                        ## المنصات

                        Instagram وWhatsApp وMessenger وTikTok مملوكة لأصحابها. وقد يغيّرون ما يستطيع Blexchat فعله أو يحدّونه، وبعض المزايا يتوقف على موافقتهم.

                        ## توفّر الخدمة

                        نعمل على إبقاء Blexchat متاحًا، لكن لا يمكننا أن نضمن عدم انقطاعه أبدًا. ولسنا مسؤولين عن الخسائر غير المباشرة، مثل المبيعات الفائتة، الناتجة عن انقطاع.

                        ## إنهاء الاستخدام

                        يمكنك إغلاق حسابك في أي وقت. وقد نعلّق الحساب الذي يخالف هذه الشروط أو قواعد إحدى المنصات.

                        ## التغييرات

                        عندما تتغير هذه الشروط تغييرًا مهمًا، نحدّث التاريخ أعلاه ونخبرك بالبريد الإلكتروني قبل أن يسري التغيير.
                        MD,
                ],
            ],
        ];
    }
}
