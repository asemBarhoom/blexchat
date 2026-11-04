<?php

namespace Database\Seeders;

use App\Models\ChannelOption;
use Illuminate\Database\Seeder;

/**
 * The channel picker (W05) as the frontend shows it (src/pages/auth/ChannelPicker.tsx). Admins edit
 * it in the panel. Safe to run again: rows are matched by platform.
 */
class ChannelOptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->channels() as $sort => $channel) {
            ChannelOption::updateOrCreate(['platform' => $channel['platform']], [...$channel, 'sort' => $sort]);
        }
    }

    /**
     * @return list<array{platform: string, name: array<string, string>, line: array<string, string>}>
     */
    private function channels(): array
    {
        return [
            [
                'platform' => 'instagram',
                'name' => ['en' => 'Instagram', 'ar' => 'Instagram'],
                'line' => ['en' => 'Comments, DMs and story replies', 'ar' => 'التعليقات والرسائل الخاصة والردود على القصص'],
            ],
            [
                'platform' => 'whatsapp',
                'name' => ['en' => 'WhatsApp', 'ar' => 'WhatsApp'],
                'line' => ['en' => 'Chats with your customers', 'ar' => 'المحادثات مع عملائك'],
            ],
            [
                'platform' => 'tiktok',
                'name' => ['en' => 'TikTok', 'ar' => 'TikTok'],
                'line' => ['en' => 'Comments and DMs on your videos', 'ar' => 'التعليقات والرسائل الخاصة على فيديوهاتك'],
            ],
            [
                'platform' => 'messenger',
                'name' => ['en' => 'Messenger', 'ar' => 'Messenger'],
                'line' => ['en' => 'Messages to your Facebook Page', 'ar' => 'الرسائل الواردة إلى صفحتك على Facebook'],
            ],
        ];
    }
}
