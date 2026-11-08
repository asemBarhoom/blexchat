<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many automations a plan may keep live at once; null for no limit. Free keeps three, as the
     * pricing table and the wizard's "Couldn't go live" notice say.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('live_automations_limit')->nullable()->after('contacts_limit');
        });

        DB::table('plans')->where('slug', 'free')->update(['live_automations_limit' => 3]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('live_automations_limit');
        });
    }
};
