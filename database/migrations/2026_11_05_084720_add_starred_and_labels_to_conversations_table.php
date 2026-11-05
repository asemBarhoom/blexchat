<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // The inbox's Favorites and Labels folders.
            $table->boolean('starred')->default(false)->after('state');
            $table->json('labels')->nullable()->after('starred');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['starred', 'labels']);
        });
    }
};
