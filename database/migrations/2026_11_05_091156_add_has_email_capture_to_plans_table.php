<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email capture is a Pro feature. Plans already stored get it from Pro up (the plans with AI),
     * so production needs no reseed.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_email_capture')->default(false)->after('has_ai');
        });

        DB::table('plans')->where('has_ai', true)->update(['has_email_capture' => true]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_email_capture');
        });
    }
};
