<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $ig   = Channel::where('user_id', $user->id)->where('platform', 'instagram')->firstOrFail();

        $posts = [
            [
                'platform_post_id' => 'CwinterAbaya001',
                'caption'          => 'عباية الشتاء وصلت ✨ اكتبي «السعر» بالتعليقات ونرسل لك التفاصيل بالخاص',
                'media_type'       => 'image',
                'posted_at'        => now()->subDays(1),
            ],
            [
                'platform_post_id' => 'CnewIn002',
                'caption'          => 'تشكيلة جديدة كل خميس 🤍',
                'media_type'       => 'reel',
                'posted_at'        => now()->subDays(4),
            ],
            [
                'platform_post_id' => 'Cchiffon003',
                'caption'          => 'طرحة شيفون ناعمة — ٨ ألوان',
                'media_type'       => 'carousel',
                'posted_at'        => now()->subDays(7),
            ],
            [
                'platform_post_id' => 'Csale004',
                'caption'          => 'خصم ٢٠٪ على كل العبايات لين الأحد',
                'media_type'       => 'image',
                'posted_at'        => now()->subDays(10),
            ],
            [
                'platform_post_id' => 'CworkLook005',
                'caption'          => 'إطلالة الدوام المريحة',
                'media_type'       => 'image',
                'posted_at'        => now()->subDays(14),
            ],
            [
                'platform_post_id' => 'Cdelivery006',
                'caption'          => 'توصيل مجاني للرياض وجدة فوق ٢٠٠ ريال',
                'media_type'       => 'image',
                'posted_at'        => now()->subDays(18),
            ],
            [
                'platform_post_id' => 'Cramadan007',
                'caption'          => 'مجموعة رمضان — قريباً 🌙',
                'media_type'       => 'image',
                'posted_at'        => now()->subMonths(2),
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
