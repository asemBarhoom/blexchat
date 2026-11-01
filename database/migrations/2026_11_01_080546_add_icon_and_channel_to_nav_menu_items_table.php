<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each menu item shows either one of our line icons or a channel's brand mark.
     */
    public function up(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table): void {
            $table->string('icon')->nullable()->after('menu');
            $table->string('channel')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table): void {
            $table->dropColumn(['icon', 'channel']);
        });
    }
};
