<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One of a solution page's starter automations and the conversation its phone preview plays. Title
     * and line are page copy (per language); the conversation is in Gulf Arabic in every language, as
     * the customer would type it. The post (an image or a typographic tile) is what the comment sits
     * under or the story replied to; the account_* columns override the page's sample business.
     */
    public function up(): void
    {
        Schema::create('solution_automations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('solution_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->json('line');
            $table->string('trigger_source')->comment('comment | dm | story');
            $table->json('trigger_words')->nullable()->comment('Empty: every comment, message or reply');
            $table->text('customer');
            $table->text('reply');
            $table->string('button', 20);
            $table->string('link');
            $table->string('post_image')->nullable();
            $table->string('post_tile_text')->nullable();
            $table->string('post_tile_tone')->nullable();
            $table->text('post_caption')->nullable();
            $table->string('post_likes')->nullable();
            $table->string('account_handle')->nullable();
            $table->string('account_avatar')->nullable();
            $table->string('account_color', 7)->nullable();
            $table->string('account_text_color', 7)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_automations');
    }
};
