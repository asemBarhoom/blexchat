<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram redelivers webhooks; the platform's message id makes storing one message twice impossible.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('platform_message_id')->nullable()->unique()->after('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique(['platform_message_id']);
            $table->dropColumn('platform_message_id');
        });
    }
};
