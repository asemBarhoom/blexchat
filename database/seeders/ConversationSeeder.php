<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The six threads of the frontend's inbox (src/pages/app/inbox/inboxContent.ts), newest first:
 * who has each one (state), the comment that opened the comment-to-DM ones, and the handover
 * on Reem's. Run after CommentSeeder; ContactSeeder fills in the people afterwards.
 */
class ConversationSeeder extends Seeder
{
    private const DM = 'أهلًا وسهلًا! 👋 هذا جاكيت الشتاء الذي سألتِ عنه — 65 دولار. المقاسات والطلب من هنا:';

    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $wa = Channel::where('user_id', $user->id)->where('platform', 'whatsapp')->firstOrFail();
        $tiktok = Channel::where('user_id', $user->id)->where('platform', 'tiktok')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();
        $comment = fn (string $id): Comment => Comment::where('channel_id', $ig->id)->where('platform_comment_id', $id)->firstOrFail();

        $fromComment = fn (Comment $comment): array => ['direction' => 'inbound', 'type' => Message::TYPE_COMMENT, 'comment_id' => $comment->id, 'content' => $comment->text, 'automated' => false, 'sent_at' => $comment->posted_at];
        $bot = fn (Carbon $sentAt, string $token, bool $clicked = false): array => [
            'direction' => 'outbound', 'type' => 'text_with_button', 'content' => self::DM,
            'button_label' => 'شاهدي المنتج', 'button_url' => 'https://nourboutique.com/winter-jacket', 'link_token' => $token,
            'link_clicks' => $clicked ? 1 : 0, 'first_clicked_at' => $clicked ? $sentAt->copy()->addMinute() : null,
            'automated' => true, 'automation_id' => $automation->id, 'sent_at' => $sentAt,
        ];
        $customer = fn (string $text, Carbon $sentAt): array => ['direction' => 'inbound', 'type' => 'text', 'content' => $text, 'automated' => false, 'sent_at' => $sentAt];

        $reem = $comment('igc_reem_001');
        $sara = $comment('igc_sara_001');
        $hala = $comment('igc_hessa_001');
        $sizeQuestion = 'طيب، هل يتوفر مقاس L؟ وأريد التوصيل إلى القاهرة قبل الخميس 🙏';

        $conversations = [
            // Reem: the automation answered her comment, then she asked what it cannot answer: Needs you.
            [
                'channel_id' => $ig->id, 'platform_conversation_id' => 'ig_conv_reem_k', 'contact_platform_id' => 'reem.k',
                'contact_name' => 'Reem Khalid', 'contact_avatar' => 'avatars/khalid.webp',
                'state' => 'human', 'labels' => ['Size question'], 'unread_count' => 1,
                'last_message' => $sizeQuestion, 'last_message_at' => now()->subMinutes(2),
                'messages' => [
                    $fromComment($reem),
                    $bot($reem->posted_at, 'seedReemWinterJacket000000000001'),
                    $customer($sizeQuestion, now()->subMinutes(2)),
                    ['direction' => Message::DIRECTION_SYSTEM, 'type' => Message::TYPE_HANDOVER, 'content' => 'unmatched', 'automated' => false, 'sent_at' => now()->subMinutes(2)],
                ],
            ],
            // Sara: answered by the automation, and she opened the jacket's page.
            [
                'channel_id' => $ig->id, 'platform_conversation_id' => 'ig_conv_sara_m', 'contact_platform_id' => 'sara.m',
                'contact_name' => 'Sara M.', 'contact_avatar' => 'avatars/sara.webp',
                'state' => 'automation', 'unread_count' => 0,
                'last_message' => self::DM, 'last_message_at' => $sara->posted_at,
                'messages' => [$fromComment($sara), $bot($sara->posted_at, 'seedSaraWinterJacket000000000001', clicked: true)],
            ],
            // Hala: answered by the automation (unread).
            [
                'channel_id' => $ig->id, 'platform_conversation_id' => 'ig_conv_hessa_q', 'contact_platform_id' => 'hala.q',
                'contact_name' => 'Hala Q.', 'contact_avatar' => 'avatars/lina.webp',
                'state' => 'automation', 'unread_count' => 1,
                'last_message' => self::DM, 'last_message_at' => $hala->posted_at,
                'messages' => [$fromComment($hala), $bot($hala->posted_at, 'seedHalaWinterJacket000000000001')],
            ],
            // Mona: WhatsApp, a repeat buyer the owner looks after (starred).
            [
                'channel_id' => $wa->id, 'platform_conversation_id' => 'wa_conv_um_faisal', 'contact_platform_id' => '+962790002210',
                'contact_name' => 'Mona F.', 'contact_avatar' => 'avatars/faisal.webp',
                'state' => 'open', 'starred' => true, 'labels' => ['Repeat buyer'], 'unread_count' => 0,
                'last_message' => 'وصلني الطلب اليوم، شكرًا لكم! 🙏', 'last_message_at' => now()->subHour(),
                'messages' => [
                    ['direction' => 'outbound', 'type' => 'text', 'content' => 'طلبك خرج مع المندوب، ويصلك اليوم إن شاء الله 🚚', 'automated' => false, 'sent_at' => now()->subHours(3)],
                    $customer('وصلني الطلب اليوم، شكرًا لكم! 🙏', now()->subHour()),
                ],
            ],
            // Noor A.: a fan (unread).
            [
                'channel_id' => $ig->id, 'platform_conversation_id' => 'ig_conv_noor_a', 'contact_platform_id' => 'noor_a',
                'contact_name' => 'Noor A.', 'contact_avatar' => 'avatars/omar.webp',
                'state' => 'open', 'unread_count' => 1,
                'last_message' => 'رائعة ما شاء الله 💜 متى يتوفر لون الموكا؟', 'last_message_at' => now()->subHours(3),
                'messages' => [$customer('رائعة ما شاء الله 💜 متى يتوفر لون الموكا؟', now()->subHours(3))],
            ],
            // Lulu: TikTok, yesterday.
            [
                'channel_id' => $tiktok->id, 'platform_conversation_id' => 'tt_conv_lulu_style', 'contact_platform_id' => 'lulu.style',
                'contact_name' => 'Lulu', 'contact_avatar' => null,
                'state' => 'open', 'unread_count' => 0,
                'last_message' => 'كم سعر الطرحة الشيفون؟ وهل يوجد شحن إلى بيروت؟', 'last_message_at' => now()->subDay(),
                'messages' => [$customer('كم سعر الطرحة الشيفون؟ وهل يوجد شحن إلى بيروت؟', now()->subDay())],
            ],
        ];

        // Lulu's thread moved from Instagram to TikTok, as in the frontend.
        Conversation::where('channel_id', $ig->id)->where('platform_conversation_id', 'ig_conv_lulu_style')->delete();

        foreach ($conversations as $data) {
            $messages = $data['messages'];
            unset($data['messages']);

            $conversation = Conversation::updateOrCreate(
                ['channel_id' => $data['channel_id'], 'platform_conversation_id' => $data['platform_conversation_id']],
                ['status' => 'open', 'starred' => false, 'labels' => [], ...$data],
            );

            // The sample thread is replaced whole, so a second run brings it up to date.
            $conversation->messages()->delete();
            foreach ($messages as $message) {
                $conversation->messages()->make()->forceFill($message)->save();
            }
        }
    }
}
