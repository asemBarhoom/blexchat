<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Trash (S5.3): a deleted automation is kept for Automation::TRASH_DAYS, then pruned.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
