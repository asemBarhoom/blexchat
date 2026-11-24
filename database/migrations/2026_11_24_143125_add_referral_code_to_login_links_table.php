<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The referral code the visitor arrived with, kept on the emailed link so it still counts
     * when the link is opened on another device.
     */
    public function up(): void
    {
        Schema::table('login_links', function (Blueprint $table): void {
            $table->string('referral_code', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('login_links', function (Blueprint $table): void {
            $table->dropColumn('referral_code');
        });
    }
};
