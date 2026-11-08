<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the owner asked to be reminded about a conversation (the inbox's Reminders folder).
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('remind_at')->nullable()->after('labels')->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('remind_at');
        });
    }
};
