<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // Blexchat AI answers what this automation's own replies do not; only on plans with AI.
            $table->boolean('ai_enabled')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('ai_enabled');
        });
    }
};
