<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The channels offered on the onboarding channel picker (W05). One row per platform; a connected
     * account lives in `channels`, keyed by the same platform.
     */
    public function up(): void
    {
        Schema::create('channel_options', function (Blueprint $table): void {
            $table->id();
            $table->string('platform')->unique()->comment('instagram | whatsapp | tiktok | messenger');
            $table->json('name');
            $table->json('line')->comment('One-line description under the name');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_options');
    }
};
