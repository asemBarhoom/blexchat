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
                'connect_another_label' => ['en' => 'Connect another channel', 'ar' => 'ربط قناة أخرى'],
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
                'channel_name' => ['en' => 'Instagram', 'ar' => 'إنستغرام'],
                'title' => ['en' => 'Your Instagram is connected!', 'ar' => 'تم ربط حسابك على إنستغرام!'],
                'lead' => [
                    'en' => 'Blexchat can now answer comments and DMs on your account. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على التعليقات والرسائل في حسابك. والآن بعض الأسئلة السريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'Instagram · Professional account', 'ar' => 'إنستغرام · حساب احترافي'],
            ],
            'whatsapp' => [
                'channel_name' => ['en' => 'WhatsApp', 'ar' => 'واتساب'],
                'title' => ['en' => 'Your WhatsApp is connected!', 'ar' => 'تم ربط واتساب الخاص بك!'],
                'lead' => [
                    'en' => 'Blexchat can now answer chats on your number. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على المحادثات على رقمك. والآن بعض الأسئلة السريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'WhatsApp Business', 'ar' => 'واتساب للأعمال'],
            ],
            'tiktok' => [
                'channel_name' => ['en' => 'TikTok', 'ar' => 'تيك توك'],
                'title' => ['en' => 'Your TikTok is connected!', 'ar' => 'تم ربط حسابك على تيك توك!'],
                'lead' => [
                    'en' => 'Blexchat can now answer comments and DMs on your videos. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على التعليقات والرسائل على فيديوهاتك. والآن بعض الأسئلة السريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'TikTok · Business account', 'ar' => 'تيك توك · حساب أعمال'],
            ],
            'messenger' => [
                'channel_name' => ['en' => 'Messenger', 'ar' => 'ماسنجر'],
                'title' => ['en' => 'Your Messenger is connected!', 'ar' => 'تم ربط ماسنجر الخاص بك!'],
                'lead' => [
                    'en' => 'Blexchat can now answer messages to your Page. Next, a few quick questions so we can suggest your first automation.',
                    'ar' => 'أصبح بإمكان Blexchat الآن الرد على الرسائل الواردة إلى صفحتك. والآن بعض الأسئلة السريعة لنقترح عليك أول أتمتة.',
                ],
                'account_kind' => ['en' => 'Facebook Page', 'ar' => 'صفحة فيسبوك'],
            ],
        ];
    }
}
