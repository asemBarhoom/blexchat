<?php

namespace Database\Seeders;

use App\Models\Solution;
use App\Models\UseCase;
use Illuminate\Database\Seeder;

/**
 * The /solutions/{slug} pages as the frontend has them (src/pages/solutions/solutionsContent.ts), with
 * Arabic page copy. The conversations stay in Gulf Arabic in both languages. A page whose slug has a
 * homepage use-case card is linked to it and takes its name, shape and tone from it; the others keep
 * their own. Every business, handle, price and link is a sample. Safe to run again: pages are matched
 * by slug, and their automations, steps and questions are replaced.
 */
class SolutionSeeder extends Seeder
{
    private const INK = '#0b0b0f';

    private const VIOLET = '#6e5bff';

    private const GREEN = '#0f7a5a';

    private const YELLOW = '#ffe83d';

    private const PINK = '#ff4fd8';

    private const WHITE = '#ffffff';

    public function run(): void
    {
        foreach ($this->pages() as $sort => $page) {
            $useCase = UseCase::where('slug', $page['slug'])->first();

            $attributes = [
                'use_case_id' => $useCase?->id,
                // The card holds these when there is one.
                'slug' => $useCase ? null : $page['slug'],
                'name' => $useCase ? null : $this->t($page['name']),
                'shape' => $useCase ? null : $page['shape'],
                'tone' => $useCase ? null : $page['tone'],
                'sample_handle' => $page['sample'][0],
                'sample_avatar' => $page['sample'][3] ?? null,
                'avatar_color' => $page['sample'][1],
                'avatar_text_color' => $page['sample'][2],
                'headline' => $this->t($page['headline']),
                'supporting' => $this->t($page['supporting']),
                'closing' => $this->t($page['closing']),
                'sort' => $sort + 1,
                'active' => true,
            ];

            $solution = Solution::forSlug($page['slug'])->first() ?? new Solution;
            $solution->fill($attributes)->save();

            $solution->automations()->delete();
            $solution->steps()->delete();
            $solution->faqItems()->delete();

            foreach ($page['automations'] as $index => $automation) {
                $solution->automations()->create([...$automation, 'sort' => $index]);
            }

            foreach ($page['steps'] as $index => $step) {
                $solution->steps()->create(['text' => $this->t($step), 'sort' => $index]);
            }

            foreach ($page['faq'] as $index => [$question, $answer]) {
                $solution->faqItems()->create(['question' => $this->t($question), 'answer' => $this->t($answer), 'sort' => $index]);
            }
        }
    }

    /**
     * @param  array{0: string, 1: string}  $pair
     * @return array{en: string, ar: string}
     */
    private function t(array $pair): array
    {
        return ['en' => $pair[0], 'ar' => $pair[1]];
    }

    /**
     * @param  array{0: string, 1: string}  $title
     * @param  array{0: string, 1: string}  $line
     * @param  list<string>  $words
     * @param  array{image?: string, tile?: array{0: string, 1: string}, caption?: string, likes?: string}|null  $post
     * @param  array{0: string, 1: string, 2: string}|null  $account  handle, avatar colour, letter colour
     * @return array<string, mixed>
     */
    private function automation(array $title, array $line, string $source, array $words, string $customer, string $reply, string $button, string $link, ?array $post = null, ?array $account = null): array
    {
        return [
            'title' => $this->t($title),
            'line' => $this->t($line),
            'trigger_source' => $source,
            'trigger_words' => $words,
            'customer' => $customer,
            'reply' => $reply,
            'button' => $button,
            'link' => $link,
            'post_image' => $post['image'] ?? null,
            'post_tile_text' => $post['tile'][0] ?? null,
            'post_tile_tone' => $post['tile'][1] ?? null,
            'post_caption' => $post['caption'] ?? null,
            'post_likes' => $post === null ? null : ($post['likes'] ?? ''),
            'account_handle' => $account[0] ?? null,
            'account_color' => $account[1] ?? null,
            'account_text_color' => $account[2] ?? null,
        ];
    }

    /**
     * A typographic post: its text on a token tone.
     *
     * @return array{tile: array{0: string, 1: string}, likes: string}
     */
    private function tile(string $text, string $tone, string $likes): array
    {
        return ['tile' => [$text, $tone], 'likes' => $likes];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'creators',
                'name' => ['Creators', 'صنّاع المحتوى'],
                'shape' => 'rounded',
                'tone' => 'brand',
                'sample' => ['mashael.moves', self::PINK, self::INK],
                'headline' => ['Your comments are a waiting list.', 'تعليقاتك قائمة انتظار.'],
                'supporting' => [
                    'Blexchat answers every comment and DM with the link, the code or the sign-up, while you keep creating.',
                    'يرد Blexchat على كل تعليق ورسالة بالرابط أو الكود أو التسجيل، وأنت مستمر في صناعة المحتوى.',
                ],
                'automations' => [
                    $this->automation(
                        ['Comment for the link', 'علّق واستلم الرابط'],
                        ['A follower comments the word, and the link arrives in their DMs at once.', 'يكتب المتابع الكلمة في التعليقات، فيصله الرابط في الخاص فوراً.'],
                        'comment', ['رابط'], 'رابط 😍',
                        'هلا والله! 👋 هذا برنامج التمارين اللي في الفيديو، وفيه كل التفاصيل:',
                        'شوفي البرنامج', 'mashaelmoves.com/program',
                        $this->tile('رابط', 'pink', '٢٬٤١٨'),
                    ),
                    $this->automation(
                        ['Story replies, answered', 'ردود الستوري تُجاب'],
                        ['Anyone who replies to a story gets your code and the way to use it.', 'كل من يرد على الستوري يستلم كودك وطريقة استخدامه.'],
                        'story', [], 'كم الخصم؟',
                        'حيّاك! 💜 هذا كود الخصم MASHAEL15، تستخدمينه عند الدفع. والمنتجات هنا:',
                        'تسوّقي الآن', 'mashaelmoves.com/picks',
                        $this->tile('MASHAEL15', 'ink', ''),
                    ),
                    $this->automation(
                        ['Welcome every DM', 'رحّب بكل رسالة'],
                        ['Greets each new message and invites them onto your list.', 'يرحّب بكل رسالة جديدة ويدعو صاحبها للانضمام إلى قائمتك.'],
                        'dm', [], 'السلام عليكم، وش أفضل تمرين للمبتدئين؟',
                        'وعليكم السلام، هلا فيك! 👋 أرسل كل أسبوع خطة تمارين مجانية على الإيميل. سجّلي من هنا:',
                        'اشتركي مجاناً', 'mashaelmoves.com/join',
                    ),
                ],
                'steps' => [
                    ['Connect the account your audience follows.', 'اربط الحساب الذي يتابعه جمهورك.'],
                    ['Pick a post and the word people should comment.', 'اختر منشوراً والكلمة التي يكتبها الناس في التعليقات.'],
                    ['Go live. Every comment gets its reply, even while you sleep.', 'شغّلها. كل تعليق يصله ردّه، حتى وأنت نائم.'],
                ],
                'faq' => [
                    [['Will my followers know it is automatic?', 'هل سيعرف متابعيّ أن الرد آلي؟'], ['The reply arrives as a normal DM from your account, in words you wrote. You decide how it sounds.', 'يصل الرد رسالةً عادية من حسابك، بكلمات كتبتها أنت. أنت من يقرر أسلوبه.']],
                    [['What if a lot of people comment at once?', 'ماذا لو علّق كثيرون في الوقت نفسه؟'], ['Each person gets their own reply. A post that takes off is the case this is built for.', 'يصل كل شخص ردّه الخاص. المنشور الذي ينتشر هو بالضبط ما صُمّم له.']],
                    [['Can I answer some messages myself?', 'هل أستطيع الرد على بعض الرسائل بنفسي؟'], ['Yes. Anything the automation does not cover lands in your inbox, marked as needing you.', 'نعم. كل ما لا تغطيه الأتمتة يصل إلى صندوق رسائلك، معلّماً بأنه يحتاجك.']],
                ],
                'closing' => ['Turn your next post into a list of people who asked for it.', 'حوّل منشورك القادم إلى قائمة بمن طلبوه.'],
            ],
            [
                'slug' => 'e-commerce',
                'name' => ['E-commerce', 'التجارة الإلكترونية'],
                'shape' => 'blob',
                'tone' => 'tint',
                'sample' => ['nourboutique', self::INK, self::WHITE, 'marketing/home/avatars/sara.webp'],
                'headline' => ['The price is asked a hundred times. Answer it once.', 'يُسأل عن السعر مئة مرة. أجب عنه مرة واحدة.'],
                'supporting' => [
                    'Blexchat sends the price, the sizes and the order link to every shopper who asks, the second they ask.',
                    'يرسل Blexchat السعر والمقاسات ورابط الطلب لكل متسوّق يسأل، في اللحظة التي يسأل فيها.',
                ],
                'automations' => [
                    $this->automation(
                        ['Price in the DMs', 'السعر في الخاص'],
                        ['A shopper comments the word, and the price and the order link land in their DMs.', 'يكتب المتسوّق الكلمة في التعليقات، فيصله السعر ورابط الطلب في الخاص.'],
                        'comment', ['السعر'], 'السعر لو سمحتي؟ 😍',
                        'هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال. المقاسات والطلب من هنا:',
                        'شوفي المنتج', 'nourboutique.com/winter-abaya',
                        ['image' => 'marketing/home/creators/huda.webp', 'caption' => 'عباية الشتاء وصلت ✨ اكتبي «السعر» بالتعليقات ونرسل لك التفاصيل بالخاص', 'likes' => '١٬٢٠٤'],
                    ),
                    $this->automation(
                        ['Offers from your stories', 'العروض من الستوري'],
                        ['A reply to a story gets the offer and the page to buy it from.', 'من يرد على الستوري يستلم العرض والصفحة التي يشتري منها.'],
                        'story', [], 'كم سعرها؟ 😍',
                        'حيّاك! 🤍 خصم ٢٠٪ على كل العبايات لين الأحد. تسوّقي العرض من هنا:',
                        'تسوّقي الآن', 'nourboutique.com/sale',
                        ['tile' => ['خصم ٢٠٪', 'ink'], 'caption' => 'خصم ٢٠٪ على كل العبايات لين الأحد', 'likes' => '٢٬٣١٥'],
                    ),
                    $this->automation(
                        ['Delivery and sizes, answered', 'التوصيل والمقاسات بلا انتظار'],
                        ['Questions about delivery and sizes get the answer page, day or night.', 'أسئلة التوصيل والمقاسات تصلها صفحة الإجابات، ليلاً ونهاراً.'],
                        'dm', ['توصيل', 'مقاس'], 'السلام عليكم، كم يوم التوصيل للدمام؟',
                        'هلا! الأسعار والمقاسات ومدة التوصيل كلها في هذي الصفحة:',
                        'الأسئلة الشائعة', 'nourboutique.com/faq',
                    ),
                ],
                'steps' => [
                    ["Connect your shop's Instagram or WhatsApp.", 'اربط إنستغرام متجرك أو واتساب.'],
                    ['Pick a post and the word shoppers should comment.', 'اختر منشوراً والكلمة التي يكتبها المتسوّقون في التعليقات.'],
                    ['Go live. Every shopper gets the price and the link at once.', 'شغّلها. كل متسوّق يستلم السعر والرابط فوراً.'],
                ],
                'faq' => [
                    [['Does it take the payment?', 'هل يستلم الدفع؟'], ['No. It sends the shopper to your own product or checkout page, where they pay as they do today.', 'لا. يرسل المتسوّق إلى صفحة منتجك أو صفحة الدفع الخاصة بك، فيدفع كما يدفع اليوم.']],
                    [['What if a post gets hundreds of comments?', 'ماذا لو وصلت المنشور مئات التعليقات؟'], ['Each shopper gets their own reply. A post that takes off is the case this is built for.', 'يصل كل متسوّق ردّه الخاص. المنشور الذي ينتشر هو بالضبط ما صُمّم له.']],
                    [['What about a question it has no answer for?', 'وماذا عن سؤال لا يملك له إجابة؟'], ['It is not answered automatically. It lands in your inbox, marked as needing you.', 'لا يُجاب آلياً. يصل إلى صندوق رسائلك، معلّماً بأنه يحتاجك.']],
                ],
                'closing' => ['Turn the next “how much?” into an order.', 'حوّل «بكم؟» القادمة إلى طلب.'],
            ],
            [
                'slug' => 'restaurants',
                'name' => ['Restaurants', 'المطاعم'],
                'shape' => 'arch',
                'tone' => 'ink',
                'sample' => ['sidra.cafe', self::INK, self::WHITE],
                'headline' => ['The table is booked before you pick up the phone.', 'تُحجز الطاولة قبل أن ترفع السمّاعة.'],
                'supporting' => [
                    "Menu, bookings and today's offer, sent to every guest who asks, while your team looks after the ones already seated.",
                    'المنيو والحجوزات وعرض اليوم، تصل لكل ضيف يسأل، بينما يهتم فريقك بمن جلسوا على الطاولات.',
                ],
                'automations' => [
                    $this->automation(
                        ['The menu, on request', 'المنيو عند الطلب'],
                        ['Someone asks for the menu under a post and gets it in their DMs.', 'يطلب أحدهم المنيو تحت منشور، فيصله في الخاص.'],
                        'comment', ['المنيو'], 'وين المنيو؟ 😋',
                        'هلا والله! 👋 هذا منيو سدرة كامل بالأسعار، وتقدر تطلب منه مباشرة:',
                        'افتح المنيو', 'sidracafe.com/menu',
                        $this->tile('المنيو', 'yellow', '٩٨٦'),
                    ),
                    $this->automation(
                        ['Book a table', 'احجز طاولة'],
                        ['A message about a booking gets the booking page, not a wait.', 'رسالة عن الحجز تصلها صفحة الحجز، بلا انتظار.'],
                        'dm', ['حجز', 'طاولة'], 'أبي أحجز طاولة لأربعة الليلة',
                        'حيّاك الله! 🤍 اختر الوقت اللي يناسبك من صفحة الحجز، ونأكد لك على طول:',
                        'احجز طاولتك', 'sidracafe.com/book',
                    ),
                    $this->automation(
                        ["Today's offer from stories", 'عرض اليوم من الستوري'],
                        ["A reply to your story gets today's offer and where to order it.", 'من يرد على الستوري يستلم عرض اليوم ومكان طلبه.'],
                        'story', [], 'كم سعر العرض؟',
                        'يا هلا! ☕ عرض اليوم: قهوة وحلا بـ ٢٩ ريال لين الساعة ١٠. اطلبه من هنا:',
                        'اطلب العرض', 'sidracafe.com/offer',
                        $this->tile('٢٩ ريال', 'ink', ''),
                    ),
                ],
                'steps' => [
                    ['Connect your Instagram or WhatsApp.', 'اربط إنستغرام أو واتساب.'],
                    ['Choose what guests ask for: the menu, a table, the offer.', 'اختر ما يسأل عنه الضيوف: المنيو، طاولة، العرض.'],
                    ['Go live. Every guest gets an answer, even during the rush.', 'شغّلها. كل ضيف يصله ردّ، حتى في وقت الذروة.'],
                ],
                'faq' => [
                    [['Does it take the order or the booking itself?', 'هل يستلم الطلب أو الحجز بنفسه؟'], ['It sends the guest straight to your menu or booking page, where the order is placed. Your existing system stays as it is.', 'يرسل الضيف مباشرة إلى المنيو أو صفحة الحجز، وهناك يتم الطلب. نظامك الحالي يبقى كما هو.']],
                    [['We change the offer every day. Is that a problem?', 'نغيّر العرض كل يوم. هل هذه مشكلة؟'], ['No. Edit the message and the link in a minute, and the next reply uses them.', 'لا. عدّل الرسالة والرابط في دقيقة، والرد التالي يستخدمهما.']],
                    [['What about a complaint or a special request?', 'وماذا عن شكوى أو طلب خاص؟'], ['Those are not answered automatically. They reach your inbox for a person to handle.', 'هذه لا تُجاب آلياً. تصل إلى صندوق رسائلك ليتولاها شخص من فريقك.']],
                ],
                'closing' => ['Answer every guest tonight, without taking anyone off the floor.', 'أجب كل ضيف الليلة، دون أن تسحب أحداً من الصالة.'],
            ],
            [
                'slug' => 'clinics',
                'name' => ['Clinics', 'العيادات'],
                'shape' => 'circle',
                'tone' => 'tint',
                'sample' => ['lamsa.dental', self::GREEN, self::WHITE],
                'headline' => ['Patients book while you are with a patient.', 'المرضى يحجزون وأنت مع مريض.'],
                'supporting' => [
                    'Blexchat answers the questions every clinic gets, prices, hours, how to book, and leaves the medical ones to you.',
                    'يجيب Blexchat عن الأسئلة التي تصل كل عيادة، الأسعار والمواعيد وطريقة الحجز، ويترك الأسئلة الطبية لك.',
                ],
                'automations' => [
                    $this->automation(
                        ['Book an appointment', 'احجز موعداً'],
                        ['A message about an appointment gets your booking page at once.', 'رسالة عن موعد تصلها صفحة الحجز فوراً.'],
                        'dm', ['موعد', 'حجز'], 'السلام عليكم، أبي موعد تنظيف أسنان',
                        'وعليكم السلام، حيّاك الله 🤍 تقدر تختار اليوم والوقت اللي يناسبك من هنا، ونرسل لك التأكيد:',
                        'احجز موعدك', 'lamsadental.com/book',
                    ),
                    $this->automation(
                        ['Prices, answered once', 'الأسعار مرة واحدة'],
                        ['A price question under a post gets the price list in a DM.', 'سؤال عن السعر تحت منشور تصله قائمة الأسعار في الخاص.'],
                        'comment', ['السعر'], 'كم السعر للتبييض؟',
                        'هلا والله! 👋 أسعار جلسات التبييض والعروض الحالية كلها في هذي الصفحة:',
                        'شوف الأسعار', 'lamsadental.com/prices',
                        $this->tile('السعر', 'surface', '٥٤٠'),
                    ),
                    $this->automation(
                        ['Location and hours', 'الموقع والمواعيد'],
                        ['Every first message gets where you are and when you are open.', 'كل رسالة أولى تصلها موقعك وأوقات عملك.'],
                        'dm', [], 'وين موقعكم؟ ومتى تفتحون؟',
                        'يا هلا! 📍 إحنا في جدة، حي الروضة. نفتح من السبت للخميس، من ٩ الصبح لين ٩ المسا. الموقع على الخريطة:',
                        'افتح الموقع', 'lamsadental.com/location',
                    ),
                ],
                'steps' => [
                    ["Connect the clinic's Instagram or WhatsApp.", 'اربط إنستغرام العيادة أو واتساب.'],
                    ['Write the answers to the questions you get every day.', 'اكتب إجابات الأسئلة التي تصلك كل يوم.'],
                    ['Go live. Booking questions are answered; the rest reach your team.', 'شغّلها. أسئلة الحجز تُجاب، والباقي يصل إلى فريقك.'],
                ],
                'faq' => [
                    [['Does it give medical advice?', 'هل يقدّم نصائح طبية؟'], ['No. It answers only what you write for it: booking, prices, hours, location. A clinical question goes to your team.', 'لا. يجيب فقط بما تكتبه له: الحجز والأسعار والمواعيد والموقع. السؤال الطبي يذهب إلى فريقك.']],
                    [['What happens to a message it cannot answer?', 'ماذا يحدث لرسالة لا يستطيع الرد عليها؟'], ['It lands in your inbox, marked as needing a person, so nothing sits unread.', 'تصل إلى صندوق رسائلك معلّمةً بأنها تحتاج شخصاً، فلا تبقى رسالة دون قراءة.']],
                    [['Can it reply in Arabic and English?', 'هل يرد بالعربية والإنجليزية؟'], ['Yes. It sends exactly what you write, so a reply can be in Arabic, in English or in both.', 'نعم. يرسل ما تكتبه بالضبط، فيمكن أن يكون الرد بالعربية أو الإنجليزية أو بكلتيهما.']],
                ],
                'closing' => ["Fill tomorrow's appointments without adding to today's workload.", 'املأ مواعيد الغد دون أن تزيد عمل اليوم.'],
            ],
            [
                'slug' => 'agencies',
                'name' => ['Agencies', 'الوكالات'],
                'shape' => 'pill',
                'tone' => 'brand',
                'sample' => ['madar.agency', self::VIOLET, self::WHITE],
                'headline' => ["One seat. Every client's inbox.", 'مقعد واحد. صندوق رسائل كل عملائك.'],
                'supporting' => [
                    "Set up replies for each client's account, answer their comments and DMs, and keep every conversation in one place.",
                    'جهّز الردود لحساب كل عميل، وأجب عن تعليقاتهم ورسائلهم، واجمع كل المحادثات في مكان واحد.',
                ],
                'automations' => [
                    $this->automation(
                        ['Launch-day replies', 'ردود يوم الإطلاق'],
                        ["On a client's launch post, every price question gets the link.", 'على منشور إطلاق لعميلك، كل سؤال عن السعر يصله الرابط.'],
                        'comment', ['السعر'], 'السعر؟',
                        'هلا والله! 👋 عطر "مسا" بـ ٣٢٠ ريال، والتوصيل مجاني هالأسبوع. اطلبه من هنا:',
                        'اطلب الآن', 'rawhperfumes.com/masa',
                        $this->tile('مسا', 'ink', '١٬٣٧٢'),
                        ['rawhperfumes', self::INK, self::WHITE],
                    ),
                    $this->automation(
                        ['Leads for a client', 'عملاء محتملون لعميلك'],
                        ["A client's new DMs get the offer and a way to book, straight away.", 'الرسائل الجديدة لعميلك تصلها العروض وطريقة الحجز فوراً.'],
                        'dm', [], 'كم الاشتراك الشهري؟',
                        'حيّاك الله! 💪 الباقات والأسعار كلها هنا، وتقدر تحجز حصة تجريبية مجانية:',
                        'احجز حصتك', 'qimmagym.com/trial',
                        null,
                        ['qimmagym', self::GREEN, self::WHITE],
                    ),
                    $this->automation(
                        ['Your own new business', 'عملاء جدد لوكالتك'],
                        ["Replies to the agency's stories get your packages and a call.", 'من يرد على ستوري الوكالة يستلم باقاتك وموعد مكالمة.'],
                        'story', [], 'أبي أعرف باقاتكم',
                        'يا هلا! 👋 هذي باقات مدار لإدارة الحسابات، واختر الوقت اللي يناسبك لمكالمة تعريفية:',
                        'احجز مكالمة', 'madar.agency/call',
                        $this->tile('مدار', 'tint', ''),
                    ),
                ],
                'steps' => [
                    ["Add each client's account to your workspace.", 'أضف حساب كل عميل إلى مساحة عملك.'],
                    ['Build their automations once, from a template or from scratch.', 'ابنِ أتمتتهم مرة واحدة، من قالب أو من الصفر.'],
                    ["Go live, and keep every client's conversations in one place.", 'شغّلها، واجمع محادثات كل عملائك في مكان واحد.'],
                ],
                'faq' => [
                    [['Can I run several client accounts from one login?', 'هل أستطيع إدارة عدة حسابات عملاء من تسجيل دخول واحد؟'], ['Yes, on the Advanced plan: many accounts, one seat.', 'نعم، في الخطة المتقدمة: حسابات كثيرة، ومقعد واحد.']],
                    [['Can my team share the work?', 'هل يستطيع فريقي تقاسم العمل؟'], ['Business and Advanced include team roles and assignment, so a conversation goes to the right person.', 'خطتا الأعمال والمتقدمة تشملان أدوار الفريق وتوزيع المحادثات، فتذهب كل محادثة إلى الشخص المناسب.']],
                    [['Can I reuse an automation for another client?', 'هل أستطيع إعادة استخدام أتمتة لعميل آخر؟'], ['Yes. Start from a template and change the words and the link for the next client.', 'نعم. ابدأ من قالب وغيّر الكلمات والرابط للعميل التالي.']],
                ],
                'closing' => ['Give every client an account that answers in seconds.', 'امنح كل عميل حساباً يرد في ثوانٍ.'],
            ],
            [
                'slug' => 'real-estate',
                'name' => ['Real estate', 'العقارات'],
                'shape' => 'house',
                'tone' => 'ink',
                'sample' => ['daralmarsa', self::INK, self::WHITE],
                'headline' => ['Know who is serious before you call.', 'اعرف من هو الجاد قبل أن تتصل.'],
                'supporting' => [
                    'Every enquiry gets the listing at once, and the details you need arrive before you spend time on a viewing.',
                    'كل استفسار يصله العقار فوراً، والتفاصيل التي تحتاجها تصلك قبل أن تقضي وقتاً في معاينة.',
                ],
                'automations' => [
                    $this->automation(
                        ['The listing, on request', 'العقار عند الطلب'],
                        ['A question under a listing gets its price, plan and photos by DM.', 'سؤال تحت إعلان عقار يصله السعر والمخطط والصور في الخاص.'],
                        'comment', ['السعر'], 'كم السعر؟ وكم غرفة؟',
                        'هلا والله! 👋 شقة غرفتين في المرسى. السعر والمخطط والصور كلها هنا:',
                        'شوف العقار', 'daralmarsa.com/marina-2br',
                        $this->tile('المرسى', 'surface', '٧٢٤'),
                    ),
                    $this->automation(
                        ['Qualify the enquiry', 'أهّل الاستفسار'],
                        ['A new enquiry gets a short form: budget, area and bedrooms.', 'كل استفسار جديد يصله نموذج قصير: الميزانية والمنطقة وعدد الغرف.'],
                        'dm', [], 'السلام عليكم، أبي شقة للإيجار في دبي',
                        'وعليكم السلام، حيّاك الله 🤍 عشان نرشّح لك الأنسب، عبّي الميزانية والمنطقة وعدد الغرف من هنا ونتصل عليك:',
                        'حدّد طلبك', 'daralmarsa.com/request',
                    ),
                    $this->automation(
                        ['Book a viewing', 'احجز معاينة'],
                        ['A reply to a property story gets the times you can show it.', 'من يرد على ستوري عقار يستلم الأوقات المتاحة للمعاينة.'],
                        'story', [], 'متى أقدر أشوفها؟',
                        'يا هلا! 🏠 المعاينة متاحة طول الأسبوع. اختر اليوم والوقت اللي يناسبك:',
                        'احجز معاينة', 'daralmarsa.com/viewing',
                        $this->tile('المرسى', 'ink', ''),
                    ),
                ],
                'steps' => [
                    ["Connect the office's Instagram or WhatsApp.", 'اربط إنستغرام المكتب أو واتساب.'],
                    ['Attach a reply to each listing you post.', 'أرفق رداً بكل عقار تنشره.'],
                    ['Go live. Enquiries arrive with a budget, an area and a time.', 'شغّلها. تصلك الاستفسارات بالميزانية والمنطقة والوقت.'],
                ],
                'faq' => [
                    [['How does it qualify a lead?', 'كيف يؤهّل العميل المحتمل؟'], ['The reply sends your own form. The buyer fills in budget, area and bedrooms before anyone calls them.', 'يرسل الرد نموذجك الخاص. يعبّئ المشتري الميزانية والمنطقة وعدد الغرف قبل أن يتصل به أحد.']],
                    [['We post new listings every week. Is that a lot of setup?', 'ننشر عقارات جديدة كل أسبوع. هل يتطلب ذلك إعداداً كثيراً؟'], ['No. Each listing needs a post, a word and a link. Start from a template and change those.', 'لا. كل عقار يحتاج منشوراً وكلمة ورابطاً. ابدأ من قالب وغيّرها.']],
                    [['Does the agent still talk to the buyer?', 'هل يبقى الوسيط على تواصل مع المشتري؟'], ['Yes. The automation handles the first question; everything after it reaches your inbox.', 'نعم. تتولى الأتمتة السؤال الأول، وكل ما بعده يصل إلى صندوق رسائلك.']],
                ],
                'closing' => ['Spend your calls on the buyers who are ready.', 'اصرف مكالماتك على المشترين الجاهزين.'],
            ],
            [
                'slug' => 'education',
                'name' => ['Education', 'التعليم'],
                'shape' => 'arch',
                'tone' => 'tint-ink',
                'sample' => ['bayan.academy', self::YELLOW, self::INK],
                'headline' => ['From a comment to an enrolment.', 'من تعليق إلى تسجيل.'],
                'supporting' => [
                    'Course details, dates and the sign-up form reach every student who asks, without a landing page in between.',
                    'تفاصيل الدورة ومواعيدها ونموذج التسجيل تصل لكل طالب يسأل، دون صفحة هبوط في المنتصف.',
                ],
                'automations' => [
                    $this->automation(
                        ['Course details on request', 'تفاصيل الدورة عند الطلب'],
                        ['A comment asking for details gets the course page in a DM.', 'تعليق يطلب التفاصيل تصله صفحة الدورة في الخاص.'],
                        'comment', ['التفاصيل'], 'أبي التفاصيل 🙏',
                        'هلا والله! 👋 هذي تفاصيل دورة المحادثة: المدة، المواعيد والرسوم. والتسجيل من نفس الصفحة:',
                        'شوف الدورة', 'bayanacademy.com/speaking',
                        $this->tile('التفاصيل', 'yellow', '٦١٥'),
                    ),
                    $this->automation(
                        ['Enrol from a DM', 'التسجيل من الخاص'],
                        ['A message about signing up gets the form and a confirmation.', 'رسالة عن التسجيل يصلها النموذج والتأكيد.'],
                        'dm', ['تسجيل', 'أسجّل'], 'كيف أسجّل في الدورة الجاية؟',
                        'حيّاك الله! 🤍 التسجيل مفتوح. عبّي بياناتك من هنا ويوصلك التأكيد على طول:',
                        'سجّل الآن', 'bayanacademy.com/enrol',
                    ),
                    $this->automation(
                        ['A free lesson first', 'درس مجاني أولاً'],
                        ['A reply to your story gets the start date and a lesson to try.', 'من يرد على الستوري يستلم موعد البداية ودرساً للتجربة.'],
                        'story', [], 'متى تبدأ الدورة؟',
                        'يا هلا! 📚 الدورة تبدأ أول الشهر. جرّب درس مجاني قبل ما تسجّل:',
                        'الدرس المجاني', 'bayanacademy.com/free-lesson',
                        $this->tile('الدرس المجاني', 'ink', ''),
                    ),
                ],
                'steps' => [
                    ["Connect the academy's Instagram or WhatsApp.", 'اربط إنستغرام الأكاديمية أو واتساب.'],
                    ['Pick the course post and the word students comment.', 'اختر منشور الدورة والكلمة التي يكتبها الطلاب في التعليقات.'],
                    ['Go live. Every student who asks gets the details and the form.', 'شغّلها. كل طالب يسأل يستلم التفاصيل والنموذج.'],
                ],
                'faq' => [
                    [['Do I need a website?', 'هل أحتاج موقعاً إلكترونياً؟'], ['You need somewhere for the link to go: a course page, a form, a payment link. Blexchat brings the student to it.', 'تحتاج مكاناً يذهب إليه الرابط: صفحة دورة، أو نموذج، أو رابط دفع. Blexchat يوصل الطالب إليه.']],
                    [['Can parents and students get different answers?', 'هل يمكن أن يستلم أولياء الأمور والطلاب إجابات مختلفة؟'], ['Yes. Set up one reply per question, each with its own words and its own link.', 'نعم. جهّز رداً لكل سؤال، لكل منها كلماته ورابطه.']],
                    [['What happens when registration closes?', 'ماذا يحدث عند إغلاق التسجيل؟'], ['Stop the automation, or change its message to the next intake. It takes a minute.', 'أوقف الأتمتة، أو غيّر رسالتها للدفعة التالية. يستغرق ذلك دقيقة.']],
                ],
                'closing' => ['Open enrolment once, and answer every question about it.', 'افتح التسجيل مرة واحدة، وأجب عن كل سؤال حوله.'],
            ],
            [
                'slug' => 'support',
                'name' => ['Support', 'خدمة العملاء'],
                'shape' => 'circle',
                'tone' => 'brand-ink',
                'sample' => ['bait.tech', self::GREEN, self::WHITE],
                'headline' => ['The same question, answered once.', 'السؤال نفسه، يُجاب مرة واحدة.'],
                'supporting' => [
                    'Order tracking, returns and delivery are answered the moment they are asked, so your team gets the questions that need a person.',
                    'تتبّع الطلبات والاسترجاع والتوصيل تُجاب لحظة السؤال عنها، فيتفرغ فريقك للأسئلة التي تحتاج شخصاً.',
                ],
                'automations' => [
                    $this->automation(
                        ['Where is my order', 'وين طلبي'],
                        ['A message about an order gets the tracking page straight away.', 'رسالة عن طلب تصلها صفحة التتبّع فوراً.'],
                        'dm', ['طلبي', 'الطلب'], 'وين طلبي؟ صار له ٣ أيام',
                        'هلا والله! 👋 تقدر تتابع طلبك أول بأول من هنا، بس اكتب رقم الطلب:',
                        'تتبّع طلبك', 'baittech.com/track',
                    ),
                    $this->automation(
                        ['Returns and warranty', 'الاسترجاع والضمان'],
                        ['A returns question gets the policy and the steps, in one reply.', 'سؤال عن الاسترجاع تصله السياسة والخطوات في رد واحد.'],
                        'dm', ['استرجاع', 'ضمان'], 'أبي استرجاع سماعة، شنو الطريقة؟',
                        'حيّاك الله 🤍 الاسترجاع خلال ١٤ يوم من الاستلام، والخطوات كلها هنا:',
                        'خطوات الاسترجاع', 'baittech.com/returns',
                    ),
                    $this->automation(
                        ['Questions under posts', 'الأسئلة تحت المنشورات'],
                        ['Stock and delivery questions in the comments get an answer by DM.', 'أسئلة التوفر والتوصيل في التعليقات تُجاب في الخاص.'],
                        'comment', ['متوفر'], 'متوفر في الكويت؟ وكم التوصيل؟',
                        'يا هلا! 📦 متوفر، والتوصيل داخل الكويت خلال يومين. التفاصيل والطلب من هنا:',
                        'شوف المنتج', 'baittech.com/x200',
                        $this->tile('X200', 'surface', '٤٥٨'),
                    ),
                ],
                'steps' => [
                    ['Connect the channels your customers write to.', 'اربط القنوات التي يراسلك عليها عملاؤك.'],
                    ['Write one reply for each question you answer every day.', 'اكتب رداً لكل سؤال تجيب عنه كل يوم.'],
                    ['Go live. Repeat questions are answered; the rest reach your team.', 'شغّلها. الأسئلة المتكررة تُجاب، والباقي يصل إلى فريقك.'],
                ],
                'faq' => [
                    [['What happens when the question is not one it knows?', 'ماذا يحدث عندما يكون السؤال غير معروف له؟'], ['It is handed to a person. The conversation appears in your inbox marked as needing you, with the reason.', 'يُحال إلى شخص. تظهر المحادثة في صندوق رسائلك معلّمةً بأنها تحتاجك، مع السبب.']],
                    [['Does it work outside working hours?', 'هل يعمل خارج أوقات الدوام؟'], ['Yes. Replies are sent at any hour; anything that needs a person waits in the inbox for the morning.', 'نعم. تُرسل الردود في أي ساعة، وما يحتاج شخصاً ينتظر في صندوق الرسائل حتى الصباح.']],
                    [['Can several people answer from the same inbox?', 'هل يستطيع عدة أشخاص الرد من صندوق الرسائل نفسه؟'], ['Pro includes an inbox for three teammates; Business adds roles and assignment.', 'خطة Pro تشمل صندوق رسائل لثلاثة من الفريق، وخطة الأعمال تضيف الأدوار وتوزيع المحادثات.']],
                ],
                'closing' => ['Let the repeat questions answer themselves.', 'دع الأسئلة المتكررة تجيب نفسها.'],
            ],
        ];
    }
}
