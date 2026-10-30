<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $user      = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig        = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $wa        = Channel::where('user_id', $user->id)->where('platform', 'whatsapp')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();

        $dm = 'هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال. المقاسات والطلب من هنا:';

        $conversations = [
            // 1 — Reem (human handover, unread)
            [
                'channel_id'               => $ig->id,
                'platform_conversation_id' => 'ig_conv_reem_k',
                'contact_name'             => 'Reem Khalid',
                'contact_avatar'           => 'avatars/khalid.webp',
                'contact_platform_id'      => 'reem.k',
                'status'                   => 'open',
                'unread_count'             => 1,
                'last_message'             => 'طيب عندكم مقاس ٥٨؟ وأبي التوصيل لجدة قبل الخميس 🙏',
                'last_message_at'          => now()->subMinutes(2),
                'messages' => [
                    ['direction' => 'inbound',  'type' => 'text', 'content' => 'السعر لو سمحتي؟ 😍',           'automated' => false, 'automation_id' => null,          'sent_at' => now()->subMinutes(7)],
                    ['direction' => 'outbound', 'type' => 'text_with_button', 'content' => $dm, 'button_label' => 'شوفي المنتج', 'button_url' => 'https://nourboutique.com/abaya-winter', 'automated' => true, 'automation_id' => $automation->id, 'sent_at' => now()->subMinutes(7)],
                    ['direction' => 'inbound',  'type' => 'text', 'content' => 'طيب عندكم مقاس ٥٨؟ وأبي التوصيل لجدة قبل الخميس 🙏', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subMinutes(2)],
                ],
            ],
            // 2 — Sara (automation handled, no unread)
            [
                'channel_id'               => $ig->id,
                'platform_conversation_id' => 'ig_conv_sara_m',
                'contact_name'             => 'Sara M.',
                'contact_avatar'           => 'avatars/sara.webp',
                'contact_platform_id'      => 'sara.m',
                'status'                   => 'open',
                'unread_count'             => 0,
                'last_message'             => $dm,
                'last_message_at'          => now()->subMinutes(14),
                'messages' => [
                    ['direction' => 'inbound',  'type' => 'text', 'content' => 'بكم هذي؟', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subMinutes(14)],
                    ['direction' => 'outbound', 'type' => 'text_with_button', 'content' => $dm, 'button_label' => 'شوفي المنتج', 'button_url' => 'https://nourboutique.com/abaya-winter', 'automated' => true, 'automation_id' => $automation->id, 'sent_at' => now()->subMinutes(14)],
                ],
            ],
            // 3 — Hessa (automation handled, unread)
            [
                'channel_id'               => $ig->id,
                'platform_conversation_id' => 'ig_conv_hessa_q',
                'contact_name'             => 'Hessa Q.',
                'contact_platform_id'      => 'hessa.q',
                'status'                   => 'open',
                'unread_count'             => 1,
                'last_message'             => $dm,
                'last_message_at'          => now()->subMinutes(41),
                'messages' => [
                    ['direction' => 'inbound',  'type' => 'text', 'content' => 'أبي أطلب، وين الرابط؟', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subMinutes(41)],
                    ['direction' => 'outbound', 'type' => 'text_with_button', 'content' => $dm, 'button_label' => 'شوفي المنتج', 'button_url' => 'https://nourboutique.com/abaya-winter', 'automated' => true, 'automation_id' => $automation->id, 'sent_at' => now()->subMinutes(41)],
                ],
            ],
            // 4 — أم فيصل (WhatsApp, repeat buyer, resolved)
            [
                'channel_id'               => $wa->id,
                'platform_conversation_id' => 'wa_conv_um_faisal',
                'contact_name'             => 'أم فيصل',
                'contact_avatar'           => 'avatars/faisal.webp',
                'contact_platform_id'      => '+966502102210',
                'status'                   => 'resolved',
                'unread_count'             => 0,
                'last_message'             => 'وصلني الطلب اليوم، تسلمون! 🙏',
                'last_message_at'          => now()->subHours(1),
                'messages' => [
                    ['direction' => 'outbound', 'type' => 'text', 'content' => 'طلبك طلع مع المندوب، يوصلك اليوم إن شاء الله 🚚', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subHours(3)],
                    ['direction' => 'inbound',  'type' => 'text', 'content' => 'وصلني الطلب اليوم، تسلمون! 🙏', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subHours(1)],
                ],
            ],
            // 5 — Noor A. (fan, unread)
            [
                'channel_id'               => $ig->id,
                'platform_conversation_id' => 'ig_conv_noor_a',
                'contact_name'             => 'Noor A.',
                'contact_platform_id'      => 'noor_a',
                'status'                   => 'open',
                'unread_count'             => 1,
                'last_message'             => 'تجنن ما شاء الله 💜 متى ينزل لون الموكا؟',
                'last_message_at'          => now()->subHours(3),
                'messages' => [
                    ['direction' => 'inbound', 'type' => 'text', 'content' => 'تجنن ما شاء الله 💜 متى ينزل لون الموكا؟', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subHours(3)],
                ],
            ],
            // 6 — Lulu (TikTok customer from Kuwait, yesterday)
            [
                'channel_id'               => $ig->id,
                'platform_conversation_id' => 'ig_conv_lulu_style',
                'contact_name'             => 'Lulu',
                'contact_platform_id'      => 'lulu.style',
                'status'                   => 'open',
                'unread_count'             => 0,
                'last_message'             => 'كم سعر الطرحة الشيفون؟ وفيه شحن للكويت؟',
                'last_message_at'          => now()->subDay(),
                'messages' => [
                    ['direction' => 'inbound', 'type' => 'text', 'content' => 'كم سعر الطرحة الشيفون؟ وفيه شحن للكويت؟', 'automated' => false, 'automation_id' => null, 'sent_at' => now()->subDay()],
                ],
            ],
        ];

        foreach ($conversations as $data) {
            $messages = $data['messages'];
            unset($data['messages']);

            $conv = Conversation::updateOrCreate(
                [
                    'channel_id'               => $data['channel_id'],
                    'platform_conversation_id' => $data['platform_conversation_id'],
                ],
                $data
            );

            // Only seed messages if the conversation was just created
            if ($conv->messages()->count() === 0) {
                foreach ($messages as $msg) {
                    $conv->messages()->create($msg);
                }
            }
        }
    }
}
