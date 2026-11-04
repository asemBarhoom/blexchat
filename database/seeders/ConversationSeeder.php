<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $wa = Channel::where('user_id', $user->id)->where('platform', 'whatsapp')->firstOrFail();
        $tiktok = Channel::where('user_id', $user->id)->where('platform', 'tiktok')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();

        $dm = 'أهلًا وسهلًا! 👋 هذا جاكيت الشتاء الذي سألتِ عنه — 65 دولار. المقاسات والطلب من هنا:';
        $bot = fn (int $minutesAgo): array => ['direction' => 'outbound', 'type' => 'text_with_button', 'content' => $dm, 'button_label' => 'شاهدي المنتج', 'button_url' => 'https://nourboutique.com/winter-jacket', 'automated' => true, 'automation_id' => $automation->id, 'sent_at' => now()->subMinutes($minutesAgo)];
        $customer = fn (string $text, mixed $sentAt): array => ['direction' => 'inbound', 'type' => 'text', 'content' => $text, 'automated' => false, 'automation_id' => null, 'sent_at' => $sentAt];

        // The six threads of the frontend's inbox (src/pages/app/inbox/inboxContent.ts), newest first.
        $conversations = [
            // 1 — Reem: answered by the automation, then a question it cannot answer (unread)
            [
                'channel_id' => $ig->id,
                'platform_conversation_id' => 'ig_conv_reem_k',
                'contact_name' => 'Reem Khalid',
                'contact_avatar' => 'avatars/khalid.webp',
                'contact_platform_id' => 'reem.k',
                'status' => 'open',
                'unread_count' => 1,
                'last_message' => 'طيب، هل يتوفر مقاس L؟ وأريد التوصيل إلى القاهرة قبل الخميس 🙏',
                'last_message_at' => now()->subMinutes(2),
                'messages' => [
                    $customer('السعر من فضلك؟ 😍', now()->subMinutes(5)),
                    $bot(5),
                    $customer('طيب، هل يتوفر مقاس L؟ وأريد التوصيل إلى القاهرة قبل الخميس 🙏', now()->subMinutes(2)),
                ],
            ],
            // 2 — Sara: handled by the automation
            [
                'channel_id' => $ig->id,
                'platform_conversation_id' => 'ig_conv_sara_m',
                'contact_name' => 'Sara M.',
                'contact_avatar' => 'avatars/sara.webp',
                'contact_platform_id' => 'sara.m',
                'status' => 'open',
                'unread_count' => 0,
                'last_message' => $dm,
                'last_message_at' => now()->subMinutes(14),
                'messages' => [$customer('بكم هذه؟', now()->subMinutes(14)), $bot(14)],
            ],
            // 3 — Hala: handled by the automation (unread)
            [
                'channel_id' => $ig->id,
                'platform_conversation_id' => 'ig_conv_hessa_q',
                'contact_name' => 'Hala Q.',
                'contact_avatar' => null,
                'contact_platform_id' => 'hala.q',
                'status' => 'open',
                'unread_count' => 1,
                'last_message' => $dm,
                'last_message_at' => now()->subMinutes(41),
                'messages' => [$customer('أريد أن أطلب، أين الرابط؟', now()->subMinutes(41)), $bot(41)],
            ],
            // 4 — Mona: WhatsApp, a repeat buyer
            [
                'channel_id' => $wa->id,
                'platform_conversation_id' => 'wa_conv_um_faisal',
                'contact_name' => 'Mona F.',
                'contact_avatar' => 'avatars/faisal.webp',
                'contact_platform_id' => '+962790002210',
                'status' => 'open',
                'unread_count' => 0,
                'last_message' => 'وصلني الطلب اليوم، شكرًا لكم! 🙏',
                'last_message_at' => now()->subHours(1),
                'messages' => [
                    ['direction' => 'outbound', 'type' => 'text', 'content' => 'طلبك خرج مع المندوب، ويصلك اليوم إن شاء الله 🚚', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subHours(3)],
                    $customer('وصلني الطلب اليوم، شكرًا لكم! 🙏', now()->subHours(1)),
                ],
            ],
            // 5 — Noor A.: a fan (unread)
            [
                'channel_id' => $ig->id,
                'platform_conversation_id' => 'ig_conv_noor_a',
                'contact_name' => 'Noor A.',
                'contact_avatar' => null,
                'contact_platform_id' => 'noor_a',
                'status' => 'open',
                'unread_count' => 1,
                'last_message' => 'رائعة ما شاء الله 💜 متى يتوفر لون الموكا؟',
                'last_message_at' => now()->subHours(3),
                'messages' => [$customer('رائعة ما شاء الله 💜 متى يتوفر لون الموكا؟', now()->subHours(3))],
            ],
            // 6 — Lulu: TikTok, yesterday
            [
                'channel_id' => $tiktok->id,
                'platform_conversation_id' => 'tt_conv_lulu_style',
                'contact_name' => 'Lulu',
                'contact_avatar' => null,
                'contact_platform_id' => 'lulu.style',
                'status' => 'open',
                'unread_count' => 0,
                'last_message' => 'كم سعر الطرحة الشيفون؟ وهل يوجد شحن إلى بيروت؟',
                'last_message_at' => now()->subDay(),
                'messages' => [$customer('كم سعر الطرحة الشيفون؟ وهل يوجد شحن إلى بيروت؟', now()->subDay())],
            ],
        ];

        // Lulu's thread moved from Instagram to TikTok, as in the frontend
        Conversation::where('channel_id', $ig->id)->where('platform_conversation_id', 'ig_conv_lulu_style')->delete();

        foreach ($conversations as $data) {
            $messages = $data['messages'];
            unset($data['messages']);

            $conv = Conversation::updateOrCreate(
                [
                    'channel_id' => $data['channel_id'],
                    'platform_conversation_id' => $data['platform_conversation_id'],
                ],
                $data
            );

            // The sample thread is replaced whole, so a second run brings it up to date
            $conv->messages()->delete();
            foreach ($messages as $msg) {
                $conv->messages()->create($msg);
            }
        }
    }
}
