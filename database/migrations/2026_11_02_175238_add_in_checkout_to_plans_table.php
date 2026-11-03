<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which plans the checkout's tier switcher offers (essential, pro and business at first).
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('in_checkout')->default(false)->after('is_recommended')->comment('Offered in the checkout switcher');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('in_checkout');
        });
    }
};
