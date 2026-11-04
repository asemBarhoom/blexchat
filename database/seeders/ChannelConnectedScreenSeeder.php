<?php

namespace Database\Seeders;

use App\Models\ChannelConnectedScreen;
use Illuminate\Database\Seeder;

/**
 * The "channel connected" screen (W07) copy per platform, as the frontend shows it
 * (src/pages/auth/Connected.tsx and connectContent.ts). Admins edit it in the panel.
 * Safe to run again: rows are matched by platform.
 */
class ChannelConnectedScreenSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->screens() as $platform => $screen) {
            ChannelConnectedScreen::updateOrCreate(['platform' => $platform], [
                ...$screen,
                'status_label' => ['en' => 'Connected', 'ar' => 'متصل'],
                'continue_label' => ['en' => 'Continue', 'ar' => 'متابعة'],
                'connect_another_label' => ['en' => 'Connect another channel', 'ar' => 'اربط قناة أخرى'],
            ]);
        }
    }

    /**
     * @return array<string, array<string, array{en: string, ar: string}>>
     */
    private function screens(): array
    {
        return [
            'instagram' => [
                'channel_name' => ['en' => 'Instagram', 'ar' => 'Instagram'],
                'title' => ['en' => 'Your Instagram is connected!', 'ar' => 'تم ربط Instagram بنجاح!'],
                'lead' => [
                    'en' => 'Blexchat can now answer comments and DMs on your account. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على التعليقات والرسائل الخاصة في حسابك. والآن بضعة أسئلة سريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'Instagram · Professional account', 'ar' => 'Instagram · حساب احترافي'],
            ],
            'whatsapp' => [
                'channel_name' => ['en' => 'WhatsApp', 'ar' => 'WhatsApp'],
                'title' => ['en' => 'Your WhatsApp is connected!', 'ar' => 'تم ربط WhatsApp بنجاح!'],
                'lead' => [
                    'en' => 'Blexchat can now answer chats on your number. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على المحادثات على رقمك. والآن بضعة أسئلة سريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'WhatsApp Business', 'ar' => 'WhatsApp Business'],
            ],
            'tiktok' => [
                'channel_name' => ['en' => 'TikTok', 'ar' => 'TikTok'],
                'title' => ['en' => 'Your TikTok is connected!', 'ar' => 'تم ربط TikTok بنجاح!'],
                'lead' => [
                    'en' => 'Blexchat can now answer comments and DMs on your videos. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على التعليقات والرسائل الخاصة على فيديوهاتك. والآن بضعة أسئلة سريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'TikTok · Business account', 'ar' => 'TikTok · حساب تجاري'],
            ],
            'messenger' => [
                'channel_name' => ['en' => 'Messenger', 'ar' => 'Messenger'],
                'title' => ['en' => 'Your Messenger is connected!', 'ar' => 'تم ربط Messenger بنجاح!'],
                'lead' => [
                    'en' => 'Blexchat can now answer messages to your Page. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على الرسائل المرسلة إلى صفحتك. والآن بضعة أسئلة سريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'Facebook Page', 'ar' => 'صفحة Facebook'],
            ],
        ];
    }
}
