<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comments on the winter-jacket post, as the frontend's phone preview shows them. Safe to run again.
 */
class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $winterPost = Post::where('channel_id', $ig->id)->where('platform_post_id', 'CwinterAbaya001')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();

        // The four comments under the post in the phone preview (src/components/preview/nourBoutique.ts).
        $comments = [
            // Comments that triggered the automation (automated_reply = true)
            [
                'platform_comment_id' => 'igc_reem_001',
                'author_name' => 'Reem Khalid',
                'author_platform_id' => 'reem.k',
                'text' => 'السعر من فضلك؟ 😍',
                'automated_reply' => true,
                'automation_id' => $automation->id,
                'posted_at' => now()->subMinutes(2),
            ],
            [
                'platform_comment_id' => 'igc_sara_001',
                'author_name' => 'Sara M.',
                'author_platform_id' => 'sara.m',
                'text' => 'بكم هذه؟',
                'automated_reply' => true,
                'automation_id' => $automation->id,
                'posted_at' => now()->subMinutes(5),
            ],
            // An organic comment that did not trigger the automation
            [
                'platform_comment_id' => 'igc_noor_001',
                'author_name' => 'Noor A.',
                'author_platform_id' => 'noor_a',
                'text' => 'رائعة ما شاء الله 💜',
                'automated_reply' => false,
                'automation_id' => null,
                'posted_at' => now()->subMinutes(9),
            ],
            [
                'platform_comment_id' => 'igc_hessa_001',
                'author_name' => 'Hala Q.',
                'author_platform_id' => 'hala.q',
                'text' => 'أريد أن أطلب، أين الرابط؟',
                'automated_reply' => true,
                'automation_id' => $automation->id,
                'posted_at' => now()->subMinutes(12),
            ],
        ];

        Comment::where('channel_id', $ig->id)->whereIn('platform_comment_id', ['igc_faisal_001', 'igc_random_001'])->delete();

        foreach ($comments as $data) {
            Comment::updateOrCreate(
                ['channel_id' => $ig->id, 'platform_comment_id' => $data['platform_comment_id']],
                array_merge($data, [
                    'post_id' => $winterPost->id,
                    'channel_id' => $ig->id,
                ])
            );
        }
    }
}
