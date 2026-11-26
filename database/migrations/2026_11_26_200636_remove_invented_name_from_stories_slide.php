<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The stories slide answered a made-up customer by name ("Thank you Lina!", from lina.m). The site
 * no longer invents people, so the scene answers any customer. Only the seeded words change: a
 * scene the admin rewrote is left as it is.
 */
return new class extends Migration
{
    /** Per language: [old handle, old reply] => [new handle, new reply]. */
    private const SCENES = [
        'en' => [['lina.m', 'Thank you Lina! Here is the link ✨'], ['your.customer', 'Thanks for the love! Here is the link ✨']],
        'ar' => [['lina.m', 'شكراً لينا! هذا الرابط ✨'], ['your.customer', 'شكرًا على تفاعلك! هذا الرابط ✨']],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        $block = DB::table('home_blocks')->where('key', 'features')->first();
        $texts = json_decode((string) $block?->texts, true);

        if (! is_array($texts)) {
            return;
        }

        foreach (self::SCENES as $locale => $scene) {
            foreach ($texts[$locale]['slides'] ?? [] as $index => $slide) {
                if (($slide['scene']['reply'] ?? null) === $scene[$from][1] && ($slide['scene']['handle'] ?? null) === $scene[$from][0]) {
                    $texts[$locale]['slides'][$index]['scene']['handle'] = $scene[$to][0];
                    $texts[$locale]['slides'][$index]['scene']['reply'] = $scene[$to][1];
                }
            }
        }

        DB::table('home_blocks')->where('key', 'features')->update(['texts' => json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
};
