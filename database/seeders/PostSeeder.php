<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The sample shop's posts, as the wizard's post picker shows them (src/components/preview/nourBoutique.ts).
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();

        $posts = [
            [
                'platform_post_id' => 'CwinterAbaya001',
                'caption' => 'جاكيت الشتاء وصل ✨ اكتبي «السعر» في التعليقات ونرسل لكِ التفاصيل في الخاص',
                'thumbnail_url' => 'posts/huda.webp',
                'media_type' => 'image',
                'like_count' => 1204,
                'comment_count' => 4,
                'posted_at' => now()->subDays(1),
            ],
            [
                'platform_post_id' => 'CnewIn002',
                'caption' => 'تشكيلة جديدة كل خميس 🤍',
                'thumbnail_url' => null,
                'media_type' => 'reel',
                'like_count' => 863,
                'comment_count' => 57,
                'posted_at' => now()->subDays(4),
            ],
            [
                'platform_post_id' => 'Cchiffon003',
                'caption' => 'طرحة شيفون ناعمة — 8 ألوان',
                'thumbnail_url' => 'posts/nora.webp',
                'media_type' => 'carousel',
                'like_count' => 940,
                'comment_count' => 61,
                'posted_at' => now()->subDays(7),
            ],
            [
                'platform_post_id' => 'Csale004',
                'caption' => 'خصم 20% على كل القطع حتى الأحد',
                'thumbnail_url' => null,
                'media_type' => 'image',
                'like_count' => 2315,
                'comment_count' => 143,
                'posted_at' => now()->subDays(10),
            ],
            [
                'platform_post_id' => 'CworkLook005',
                'caption' => 'إطلالة العمل المريحة',
                'thumbnail_url' => 'posts/lina.webp',
                'media_type' => 'image',
                'like_count' => 712,
                'comment_count' => 22,
                'posted_at' => now()->subDays(14),
            ],
            [
                'platform_post_id' => 'Cdelivery006',
                'caption' => 'توصيل مجاني لكل المدن للطلبات فوق 50 دولار',
                'thumbnail_url' => null,
                'media_type' => 'image',
                'like_count' => 598,
                'comment_count' => 18,
                'posted_at' => now()->subDays(18),
            ],
            [
                'platform_post_id' => 'Cramadan007',
                'caption' => 'مجموعة رمضان — قريباً 🌙',
                'thumbnail_url' => null,
                'media_type' => 'image',
                'like_count' => 1047,
                'comment_count' => 89,
                'posted_at' => now()->subMonths(2),
            ],
            [
                'platform_post_id' => 'Csizes008',
                'caption' => 'متوفّرة الآن مقاسات XS و XXL',
                'thumbnail_url' => null,
                'media_type' => 'image',
                'like_count' => 430,
                'comment_count' => 12,
                'posted_at' => now()->subMonths(3),
            ],
        ];

        foreach ($posts as $data) {
            Post::updateOrCreate(
                ['channel_id' => $ig->id, 'platform_post_id' => $data['platform_post_id']],
                array_merge($data, ['channel_id' => $ig->id])
            );
        }
    }
}
