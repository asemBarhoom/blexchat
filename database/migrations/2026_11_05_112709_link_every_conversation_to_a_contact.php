<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Conversations from before contacts were kept get theirs, so every conversation has a contact.
     * An existing contact with the same channel and platform id is reused.
     */
    public function up(): void
    {
        DB::table('conversations')
            ->join('channels', 'channels.id', '=', 'conversations.channel_id')
            ->whereNull('conversations.contact_id')
            ->select('conversations.*', 'channels.user_id', 'channels.platform')
            ->orderBy('conversations.id')
            ->each(function (object $conversation): void {
                $platformId = $conversation->contact_platform_id ?? $conversation->platform_conversation_id;

                $contactId = DB::table('contacts')
                    ->where('channel_id', $conversation->channel_id)
                    ->where('platform_id', $platformId)
                    ->value('id');

                $contactId ??= DB::table('contacts')->insertGetId([
                    'user_id' => $conversation->user_id,
                    'channel_id' => $conversation->channel_id,
                    'platform' => $conversation->platform,
                    'platform_id' => $platformId,
                    'name' => $conversation->contact_name ?? $platformId,
                    'handle' => $conversation->contact_name,
                    'avatar' => $conversation->contact_avatar,
                    'tags' => json_encode([]),
                    'subscribed' => true,
                    'added_manually' => false,
                    'first_seen_at' => $conversation->created_at ?? now(),
                    'last_active_at' => $conversation->last_message_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('conversations')->where('id', $conversation->id)->update(['contact_id' => $contactId]);
            });
    }

    /**
     * The links are kept: they are correct either way.
     */
    public function down(): void {}
};
