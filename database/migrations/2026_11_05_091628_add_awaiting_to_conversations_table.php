<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The step of an automation's paid extras that waits on the customer's next message.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('awaiting')->nullable()->after('state');
            $table->foreignId('awaiting_automation_id')->nullable()->after('awaiting')->constrained('automations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awaiting_automation_id');
            $table->dropColumn('awaiting');
        });
    }
};
