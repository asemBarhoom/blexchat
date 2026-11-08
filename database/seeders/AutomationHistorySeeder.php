<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The months before the frontend's six: eighteen earlier customers of @nourboutique, so every
 * automation has real Runs and a CTR close to the list's (src/pages/app/automationsContent.ts).
 * First DM → welcome answered nine of them (22%), Story reply → thank you answered eight of the
 * same people thirteen times (38%), and the Ramadan comment-to-DM answered nine others in March,
 * seventeen comments in all (41%). Everything is resolved and older than ten days, so the inbox
 * and Home's last 7 days still lead with the six. Run after ContactSeeder; safe to run again.
 */
class AutomationHistorySeeder extends Seeder
{
    /** name, handle, city, phone, gender */
    private const PEOPLE = [
        ['Layla Hassan', 'layla.hassan', 'Cairo', '+20 11 4720 3381', 'female'],
        ['Yasmin Adel', 'yasmin.adel', 'Alexandria', '+20 12 2195 7740', 'female'],
        ['Dana Saleh', 'dana.saleh', 'Amman', '+962 78 551 2093', 'female'],
        ['Rana Haddad', 'rana.haddad', 'Beirut', '+961 70 318 446', 'female'],
        ['Mariam Toumi', 'mariam.toumi', 'Tunis', '+216 98 204 715', 'female'],
        ['Salma Benali', 'salma.benali', 'Casablanca', '+212 662 418 093', 'female'],
        ['Aya Mansour', 'aya.mansour', 'Cairo', '+20 10 6612 0457', 'female'],
        ['Farah Nasser', 'farah.nasser', 'Amman', '+962 79 640 1182', 'female'],
        ['Omar Zaid', 'omar.zaid', 'Amman', '+962 77 903 5521', 'male'],
        ['Huda Karim', 'huda.karim', 'Kuwait City', '+965 6512 3390', 'female'],
        ['Lina Fares', 'lina.fares', 'Beirut', '+961 76 552 019', 'female'],
        ['Nada Youssef', 'nada.youssef', 'Giza', '+20 11 0938 6624', 'female'],
        ['Rasha Amin', 'rasha.amin', 'Doha', '+974 5512 8834', 'female'],
        ['Tala Odeh', 'tala.odeh', 'Irbid', '+962 78 112 4409', 'female'],
        ['Jana Khoury', 'jana.khoury', 'Beirut', '+961 71 884 270', 'female'],
        ['Malak Idrissi', 'malak.idrissi', 'Rabat', '+212 667 350 812', 'female'],
        ['Ahmad Saber', 'ahmad.saber', 'Cairo', '+20 12 7741 2085', 'male'],
        ['Ines Gharbi', 'ines.gharbi', 'Sfax', '+216 25 663 190', 'female'],
    ];

    private const FIRST_MESSAGES = [
        'مرحبا، عندكم شحن لعمّان؟',
        'السلام عليكم، كيف أطلب؟',
        'فيه مقاس S من العباية؟',
        'مرحبا 👋 عندكم ألوان جديدة؟',
        'كم مدة التوصيل إلى تونس؟',
        'أهلًا، هل الدفع عند الاستلام متاح؟',
        'بدي أشوف التشكيلة الجديدة',
        'مرحبا، عندكم خصومات هالأسبوع؟',
        'بدي هدية لأختي، شو بتنصحوني؟',
    ];

    private const STORY_REPLIES = ['😍😍', 'كم سعرها؟', 'روعة 🤍', 'متوفرة بالأسود؟', 'بدي هي!'];

    private const RAMADAN_COMMENTS = ['رمضان 🌙', 'رمضان، كم السعر؟'];

    private const WELCOME = 'أهلًا بكِ في Nour Boutique 🤍 هذه أحدث تشكيلتنا، والطلب من هنا:';

    private const STORY_THANKS = 'شكرًا لتفاعلك مع القصة 🤍 هذا رابط القطعة:';

    private const RAMADAN_DM = 'مجموعة رمضان وصلت 🌙 هذه القطع والأسعار:';

    private const PUBLIC_REPLY = 'أرسلنا لك التفاصيل بالخاص 💌';

    private int $token = 0;

    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $ramadanPost = Post::where('channel_id', $ig->id)->where('platform_post_id', 'Cramadan007')->firstOrFail();
        $automation = fn (string $name): Automation => Automation::where('user_id', $user->id)->where('name', $name)->firstOrFail();
        $welcome = $automation('First DM → welcome and catalogue');
        $story = $automation('Story reply → thank you + link');
        $ramadan = $automation('Ramadan collection — comment to DM');

        Conversation::where('channel_id', $ig->id)->where('platform_conversation_id', 'like', 'ig_hist_%')->delete();
        Comment::where('channel_id', $ig->id)->where('platform_comment_id', 'like', 'igc_hist_%')->delete();

        $welcomeClicks = 2;
        $storySends = 0;
        $ramadanSends = 0;

        foreach (self::PEOPLE as $index => [$name, $handle, $city, $phone, $gender]) {
            $conversation = Conversation::create([
                'channel_id' => $ig->id,
                'platform_conversation_id' => "ig_hist_{$handle}",
                'contact_platform_id' => $handle,
                'contact_name' => $name,
                'status' => 'resolved',
                'state' => 'automation',
                'unread_count' => 0,
            ]);

            if ($index < 9) {
                // Started by a DM in the last five months; the welcome answered it.
                $startedAt = now()->subDays(140 - $index * 13)->setTime(19 + $index % 4, 10 + $index * 3);
                $source = $welcome;
                $this->line($conversation, ['direction' => 'inbound', 'content' => self::FIRST_MESSAGES[$index]], $startedAt);
                $this->dm($conversation, $welcome, self::WELCOME, 'تصفّحي الجديد', 'https://nourboutique.com/new-in', $startedAt, clicked: $index < $welcomeClicks);

                // A month on, the first eight replied to stories: five of them twice.
                $replies = $index < 5 ? 2 : ($index < 8 ? 1 : 0);
                for ($reply = 0; $reply < $replies; $reply++) {
                    $at = $startedAt->copy()->addDays(30 + $reply * 17);
                    $this->line($conversation, ['direction' => 'inbound', 'content' => self::STORY_REPLIES[($index + $reply) % count(self::STORY_REPLIES)]], $at);
                    $this->dm($conversation, $story, self::STORY_THANKS, 'شاهدي القطعة', 'https://nourboutique.com/abaya-winter', $at, clicked: $storySends++ < 5);
                }
            } else {
                // Commented "رمضان" under the Ramadan post in March; all but the last commented twice.
                $startedAt = now()->setDate(now()->year, 2, 22 + ($index - 9))->setTime(21, 5 + $index * 2);
                $source = $ramadan;
                $comments = $index < 17 ? 2 : 1;

                for ($number = 0; $number < $comments; $number++) {
                    $at = $startedAt->copy()->addDays($number * 6);
                    $comment = Comment::create([
                        'channel_id' => $ig->id,
                        'post_id' => $ramadanPost->id,
                        'platform_comment_id' => "igc_hist_{$handle}_{$number}",
                        'author_name' => $handle,
                        'author_platform_id' => $handle,
                        'text' => self::RAMADAN_COMMENTS[$number],
                        'automated_reply' => true,
                        'automation_id' => $ramadan->id,
                        'posted_at' => $at,
                    ]);
                    $this->line($conversation, ['direction' => 'inbound', 'type' => Message::TYPE_COMMENT, 'comment_id' => $comment->id, 'content' => $comment->text], $at);
                    $this->dm($conversation, $ramadan, self::RAMADAN_DM, 'شاهدي المجموعة', 'https://nourboutique.com/ramadan', $at, clicked: $ramadanSends++ < 7);
                }
            }

            $this->line($conversation, ['direction' => 'inbound', 'content' => 'شكرًا 🙏'], Carbon::parse($conversation->messages()->max('sent_at'))->addMinutes(4));
            $last = $conversation->messages()->latest('sent_at')->latest('id')->firstOrFail();
            $conversation->update(['last_message' => $last->content, 'last_message_at' => $last->sent_at]);

            Contact::where('id', $conversation->contact_id)->update([
                'name' => $name,
                'handle' => $handle,
                'phone' => $phone,
                'email' => "{$handle}@gmail.com",
                'city' => $city,
                'gender' => $gender,
                'language' => 'ar',
                'tags' => json_encode($source->is($ramadan) ? ['Ramadan collection', 'Asked price'] : ($index < 8 ? ['Customer', 'Fan'] : ['Customer']), JSON_UNESCAPED_UNICODE),
                'source_automation_id' => $source->id,
                'first_seen_at' => $startedAt,
                'last_active_at' => $last->sent_at,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function line(Conversation $conversation, array $attributes, Carbon $sentAt): void
    {
        $conversation->messages()->make()->forceFill(['type' => 'text', 'automated' => false, ...$attributes, 'sent_at' => $sentAt])->save();
    }

    /**
     * The automation's DM a minute after what it answered, with its tracked button.
     */
    private function dm(Conversation $conversation, Automation $automation, string $text, string $button, string $url, Carbon $answering, bool $clicked): void
    {
        $sentAt = $answering->copy()->addMinute();

        $this->line($conversation, [
            'direction' => 'outbound',
            'type' => 'text_with_button',
            'content' => $text,
            'button_label' => $button,
            'button_url' => $url,
            'link_token' => 'seedHistory'.str_pad((string) ++$this->token, 21, '0', STR_PAD_LEFT),
            'link_clicks' => $clicked ? 1 : 0,
            'first_clicked_at' => $clicked ? $sentAt->copy()->addMinutes(2) : null,
            'automated' => true,
            'automation_id' => $automation->id,
        ], $sentAt);
    }
}
