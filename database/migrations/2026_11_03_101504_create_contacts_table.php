<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null for someone added by hand, and kept when a channel is disconnected.
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform'); // instagram | whatsapp | tiktok | messenger
            $table->string('platform_id')->nullable();
            $table->string('name');
            $table->string('handle')->nullable();
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('gender')->nullable(); // female | male
            $table->string('language')->nullable();
            // Text, not json, so search matches tags case-insensitively in every database.
            $table->text('tags')->nullable();
            $table->boolean('subscribed')->default(true);
            $table->foreignId('source_automation_id')->nullable()->constrained('automations')->nullOnDelete();
            $table->boolean('added_manually')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'platform_id']);
            $table->index(['user_id', 'last_active_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
