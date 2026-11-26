<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many times each automation ran: once per comment or message it answered, however many
 * messages that run sent. The list showed every message sent until now, so a comment that got a
 * public reply and a DM counted twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->unsignedInteger('runs_count')->default(0)->after('ai_enabled');
        });

        // The past as well as it can be told: a comment automation ran once per comment it answered;
        // a DM or story automation, once per message it sent, as one run seldom sends more than one.
        DB::table('automations')->select(['id', 'trigger_type'])->orderBy('id')->each(function (object $automation): void {
            $runs = $automation->trigger_type === 'comment_keyword'
                ? DB::table('comments')->where('automation_id', $automation->id)->where('automated_reply', true)->count()
                : DB::table('messages')->where('automation_id', $automation->id)->where('automated', true)->count();

            DB::table('automations')->where('id', $automation->id)->update(['runs_count' => $runs]);
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('runs_count');
        });
    }
};
