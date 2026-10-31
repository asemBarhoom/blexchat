<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Channel tokens grant access to customers' inboxes; Channel now casts them as encrypted,
     * so tokens stored before that are encrypted in place.
     */
    public function up(): void
    {
        DB::table('channels')->select(['id', 'access_token'])->orderBy('id')->each(function (object $channel): void {
            try {
                Crypt::decryptString($channel->access_token);
            } catch (DecryptException) {
                DB::table('channels')->where('id', $channel->id)->update(['access_token' => Crypt::encryptString($channel->access_token)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('channels')->select(['id', 'access_token'])->orderBy('id')->each(function (object $channel): void {
            try {
                DB::table('channels')->where('id', $channel->id)->update(['access_token' => Crypt::decryptString($channel->access_token)]);
            } catch (DecryptException) {
                // Already plain text.
            }
        });
    }
};
