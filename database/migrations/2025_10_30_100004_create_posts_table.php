<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('platform_post_id');
            $table->text('caption')->nullable();
            $table->string('media_type')->nullable(); // image | video | carousel | reel
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'platform_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
