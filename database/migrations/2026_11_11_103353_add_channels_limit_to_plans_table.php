<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many channels an owner may connect, every workspace counted together; null for no limit.
     * Free keeps one, as the pricing table says.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('channels_limit')->nullable()->after('contacts_limit');
        });

        DB::table('plans')->where('slug', 'free')->update(['channels_limit' => 1]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('channels_limit');
        });
    }
};
