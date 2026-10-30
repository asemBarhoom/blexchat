<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('platform_comment_id');
            $table->string('author_name')->nullable();
            $table->string('author_platform_id');
            $table->text('text');
            $table->boolean('automated_reply')->default(false);
            $table->foreignId('automation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['channel_id', 'platform_comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
