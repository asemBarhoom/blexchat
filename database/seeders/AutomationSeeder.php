<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\AutomationFolder;
use App\Models\Channel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * @nourboutique's five automations, as the frontend lists them (src/pages/app/automationsContent.ts)
 * with the wizard answers it keeps for each (src/app/automationsStore.ts), in the list's folders
 * (Instagram, Seasonal). Run after PostSeeder: the comment automations watch the winter-jacket
 * post, the Ramadan one its own post. `updated_at` is the list's "Modified".
 */
class AutomationSeeder extends Seeder
{
    private const DM_TEXT = 'هلا والله! 👋 هذي عباية الشتاء اللي سألتِ عنها — ٢٤٩ ريال. المقاسات والطلب من هنا:';

    private const DM_BUTTON = 'شوفي المنتج';

    private const PUBLIC_REPLY = 'أرسلنا لك التفاصيل بالخاص 💌';

    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $winterPost = Post::where('channel_id', $ig->id)->where('platform_post_id', 'CwinterAbaya001')->firstOrFail();
        $ramadanPost = Post::where('channel_id', $ig->id)->where('platform_post_id', 'Cramadan007')->firstOrFail();
        $folder = fn (string $name): int => AutomationFolder::firstOrCreate(['user_id' => $user->id, 'name' => $name])->id;
        $instagram = $folder('Instagram');
        $seasonal = $folder('Seasonal');

        $automations = [
            // a1 — the one that just went live
            [
                'name' => 'Comment "PRICE" → send the catalogue',
                'trigger_type' => 'comment_keyword',
                'keywords' => ['السعر'],
                'link' => 'https://nourboutique.com/abaya-winter',
                'active' => true,
                'folder' => $instagram,
                'post' => $winterPost,
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
            // a2
            [
                'name' => 'Story reply → thank you + link',
                'trigger_type' => 'story_mention',
                'keywords' => [],
                'link' => 'https://nourboutique.com/abaya-winter',
                'active' => true,
                'folder' => $instagram,
                'created_at' => now()->subMonths(4),
                'updated_at' => now()->subDays(2),
            ],
            // a3
            [
                'name' => 'First DM → welcome and catalogue',
                'trigger_type' => 'dm_keyword',
                'keywords' => [],
                'link' => 'https://nourboutique.com/new-in',
                'active' => true,
                'folder' => $instagram,
                'created_at' => now()->subMonths(5),
                'updated_at' => now()->subDays(6),
            ],
            // a4 — seasonal, stopped
            [
                'name' => 'Ramadan collection — comment to DM',
                'trigger_type' => 'comment_keyword',
                'keywords' => ['رمضان'],
                'link' => 'https://nourboutique.com/ramadan',
                'active' => false,
                'folder' => $seasonal,
                'post' => $ramadanPost,
                'created_at' => now()->setDate(now()->year, 2, 20),
                'updated_at' => now()->setDate(now()->year, 3, 18),
            ],
            // a5 — seasonal, stopped, never ran
            [
                'name' => 'Eid restock waitlist',
                'trigger_type' => 'comment_keyword',
                'keywords' => ['تنبيه'],
                'link' => 'https://nourboutique.com/waitlist',
                'active' => false,
                'folder' => $seasonal,
                'post' => $winterPost,
                'created_at' => now()->setDate(now()->year, 4, 2),
                'updated_at' => now()->setDate(now()->year, 4, 2),
            ],
        ];

        foreach ($automations as $automation) {
            $isComment = $automation['trigger_type'] === 'comment_keyword';

            // forceFill: the dates are the list's own, and not mass assignable.
            Automation::firstOrNew(['user_id' => $user->id, 'name' => $automation['name']])->forceFill(
                [
                    'channel_id' => $ig->id,
                    'automation_folder_id' => $automation['folder'],
                    'trigger_type' => $automation['trigger_type'],
                    'post_mode' => $isComment ? 'specific' : null,
                    'post_id' => $isComment ? $automation['post']->id : null,
                    'keywords' => $automation['keywords'],
                    'active' => $automation['active'],
                    'actions' => [
                        ...($isComment ? [['type' => 'reply_comment', 'message' => self::PUBLIC_REPLY]] : []),
                        [
                            'type' => 'send_dm_with_button',
                            'message' => self::DM_TEXT,
                            'button_label' => self::DM_BUTTON,
                            'button_url' => $automation['link'],
                        ],
                    ],
                    'extras' => ['ask_follow' => false, 'ask_email' => false, 'follow_up' => false],
                    'created_at' => $automation['created_at'],
                    'updated_at' => $automation['updated_at'],
                ]
            )->save();
        }
    }
}
