<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;

class AutomationSeeder extends Seeder
{
    public function run(): void
    {
        $user    = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig      = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $wa      = Channel::where('user_id', $user->id)->where('platform', 'whatsapp')->firstOrFail();

        // a1 — Comment "السعر" → send the catalogue (the hero scenario)
        Automation::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Comment "PRICE" → send the catalogue'],
            [
                'channel_id'   => $ig->id,
                'trigger_type' => 'comment_keyword',
                'keywords'     => ['السعر', 'رابط', 'طلب', 'بكم'],
                'active'       => true,
                'actions'      => [
                    [
                        'type'    => 'reply_comment',
                        'message' => 'أرسلنا لك التفاصيل بالخاص 💌',
                    ],
                    [
                        'type'         => 'send_dm_with_button',
                        'message'      => 'هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال. المقاسات والطلب من هنا:',
                        'button_label' => 'شوفي المنتج',
                        'button_url'   => 'https://nourboutique.com/abaya-winter',
                    ],
                ],
                'created_at' => now()->subMinutes(5),
            ]
        );

        // a2 — Story reply → thank you + link
        Automation::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Story reply → thank you + link'],
            [
                'channel_id'   => $ig->id,
                'trigger_type' => 'story_mention',
                'keywords'     => null,
                'active'       => true,
                'actions'      => [
                    [
                        'type'         => 'send_dm_with_button',
                        'message'      => 'يسعدنا ردّك 💜 هذي القطعة اللي في الستوري، بالسعر والمقاسات:',
                        'button_label' => 'شوفي القطعة',
                        'button_url'   => 'https://nourboutique.com/abaya-winter',
                    ],
                ],
                'created_at' => now()->subDays(2),
            ]
        );

        // a3 — First DM → welcome and catalogue
        Automation::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'First DM → welcome and catalogue'],
            [
                'channel_id'   => $ig->id,
                'trigger_type' => 'dm_keyword',
                'keywords'     => [],
                'active'       => true,
                'actions'      => [
                    [
                        'type'         => 'send_dm_with_button',
                        'message'      => 'هلا والله! 👋 وصلتنا رسالتك ونرد عليك بأسرع وقت. لين ذاك الوقت، كل المنتجات والأسعار هنا:',
                        'button_label' => 'تصفّحي المتجر',
                        'button_url'   => 'https://nourboutique.com/shop',
                    ],
                ],
                'created_at' => now()->subDays(6),
            ]
        );

        // a4 — Ramadan collection — stopped (seasonal)
        Automation::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Ramadan collection — comment to DM'],
            [
                'channel_id'   => $ig->id,
                'trigger_type' => 'comment_keyword',
                'keywords'     => ['رمضان', 'كولكشن', 'مجموعة'],
                'active'       => false,
                'actions'      => [
                    [
                        'type'         => 'send_dm_with_button',
                        'message'      => 'هلا والله! 💜 مجموعة رمضان وصلت — المقاسات والأسعار من هنا:',
                        'button_label' => 'شوفي المجموعة',
                        'button_url'   => 'https://nourboutique.com/ramadan',
                    ],
                ],
                'created_at' => now()->subWeeks(6),
            ]
        );

        // a5 — Eid restock waitlist — stopped
        Automation::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Eid restock waitlist'],
            [
                'channel_id'   => $ig->id,
                'trigger_type' => 'comment_keyword',
                'keywords'     => ['عيد', 'ريستوك', 'متى يرجع'],
                'active'       => false,
                'actions'      => [
                    [
                        'type'    => 'send_dm',
                        'message' => 'هلا 🌙 حطينا اسمك في قائمة الانتظار — نعلمك أول ما يرجع المنتج!',
                    ],
                ],
                'created_at' => now()->subWeeks(4),
            ]
        );
    }
}
