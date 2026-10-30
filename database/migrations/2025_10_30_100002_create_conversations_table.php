<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('platform_conversation_id');
            $table->string('contact_name')->nullable();
            $table->string('contact_avatar')->nullable();
            $table->string('contact_platform_id');
            $table->string('status')->default('open'); // open | resolved
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'platform_conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
