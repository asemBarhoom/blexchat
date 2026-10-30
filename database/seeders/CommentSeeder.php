<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $user       = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig         = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();
        $winterPost = Post::where('channel_id', $ig->id)->where('platform_post_id', 'CwinterAbaya001')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();

        $comments = [
            // Comments that triggered the automation (automated_reply = true)
            [
                'platform_comment_id' => 'igc_reem_001',
                'author_name'         => 'Reem Khalid',
                'author_platform_id'  => 'reem.k',
                'text'                => 'السعر لو سمحتي؟ 😍',
                'automated_reply'     => true,
                'automation_id'       => $automation->id,
                'posted_at'           => now()->subHours(1)->subMinutes(5),
            ],
            [
                'platform_comment_id' => 'igc_sara_001',
                'author_name'         => 'Sara M.',
                'author_platform_id'  => 'sara.m',
                'text'                => 'بكم هذي؟',
                'automated_reply'     => true,
                'automation_id'       => $automation->id,
                'posted_at'           => now()->subHours(1)->subMinutes(14),
            ],
            [
                'platform_comment_id' => 'igc_hessa_001',
                'author_name'         => 'Hessa Q.',
                'author_platform_id'  => 'hessa.q',
                'text'                => 'أبي أطلب، وين الرابط؟',
                'automated_reply'     => true,
                'automation_id'       => $automation->id,
                'posted_at'           => now()->subHours(1)->subMinutes(41),
            ],
            // Organic comments that did not trigger the automation
            [
                'platform_comment_id' => 'igc_noor_001',
                'author_name'         => 'Noor A.',
                'author_platform_id'  => 'noor_a',
                'text'                => 'تجنن ما شاء الله 💜',
                'automated_reply'     => false,
                'automation_id'       => null,
                'posted_at'           => now()->subHours(3),
            ],
            [
                'platform_comment_id' => 'igc_faisal_001',
                'author_name'         => 'faisal.a',
                'author_platform_id'  => 'faisal.a',
                'text'                => 'يسلمو كل شي وصل زين 🙏',
                'automated_reply'     => false,
                'automation_id'       => null,
                'posted_at'           => now()->subHours(5),
            ],
            [
                'platform_comment_id' => 'igc_random_001',
                'author_name'         => 'لولو',
                'author_platform_id'  => 'lulu.style',
                'text'                => 'تتوصلون للكويت؟',
                'automated_reply'     => false,
                'automation_id'       => null,
                'posted_at'           => now()->subHours(8),
            ],
        ];

        foreach ($comments as $data) {
            Comment::updateOrCreate(
                ['channel_id' => $ig->id, 'platform_comment_id' => $data['platform_comment_id']],
                array_merge($data, [
                    'post_id'    => $winterPost->id,
                    'channel_id' => $ig->id,
                ])
            );
        }
    }
}
