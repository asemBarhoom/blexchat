<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The copy of the "channel connected" screen (W07), one row per platform. Every text is
     * translatable; `account_kind` is the line under the connected account's handle.
     */
    public function up(): void
    {
        Schema::create('channel_connected_screens', function (Blueprint $table): void {
            $table->id();
            $table->string('platform')->unique()->comment('instagram | whatsapp | tiktok | messenger');
            $table->json('channel_name');
            $table->json('title');
            $table->json('lead');
            $table->json('account_kind');
            $table->json('status_label');
            $table->json('continue_label');
            $table->json('connect_another_label');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connected_screens');
    }
};
