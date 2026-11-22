<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram's CDN links for thumbnails and profile pictures run well past 255 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('thumbnail_url')->nullable()->change();
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->text('profile_picture')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('thumbnail_url')->nullable()->change();
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->string('profile_picture')->nullable()->change();
        });
    }
};
