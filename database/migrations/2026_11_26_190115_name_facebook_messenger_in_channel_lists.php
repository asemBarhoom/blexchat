<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The channel lists in the site's copy name Facebook Messenger rather than Messenger: one Facebook
 * Page connection answers both its Messenger DMs and its post comments, so it stays one channel of
 * four. Only the seeded sentences change; copy the admin rewrote is left as it is.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAMED = [
        'Instagram, WhatsApp, TikTok, Messenger' => 'Instagram, WhatsApp, TikTok, Facebook Messenger',
        'Instagram, WhatsApp, TikTok and Messenger' => 'Instagram, WhatsApp, TikTok and Facebook Messenger',
        'Instagram, WhatsApp, TikTok & Messenger' => 'Instagram, WhatsApp, TikTok & Facebook Messenger',
        'إنستغرام وواتساب وتيك توك وماسنجر' => 'إنستغرام وواتساب وتيك توك وفيسبوك ماسنجر',
        'Instagram وWhatsApp وTikTok وMessenger' => 'Instagram وWhatsApp وTikTok وFacebook Messenger',
    ];

    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'home_blocks' => ['texts'],
        'auth_screens' => ['texts'],
        'faq_items' => ['question', 'answer'],
        'legal_pages' => ['body'],
    ];

    public function up(): void
    {
        $this->replace(self::RENAMED);
    }

    public function down(): void
    {
        $this->replace(array_flip(self::RENAMED));
    }

    /**
     * @param  array<string, string>  $pairs
     */
    private function replace(array $pairs): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach (DB::table($table)->select(['id', ...$columns])->get() as $row) {
                $changes = [];

                foreach ($columns as $column) {
                    $value = json_decode((string) $row->{$column}, true);

                    if (! is_array($value)) {
                        continue;
                    }

                    $renamed = $value;
                    array_walk_recursive($renamed, function (mixed &$text) use ($pairs): void {
                        if (is_string($text)) {
                            $text = strtr($text, $pairs);
                        }
                    });

                    if ($renamed !== $value) {
                        $changes[$column] = json_encode($renamed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        }
    }
};
