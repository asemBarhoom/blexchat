<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // Deleting a folder leaves its automations in All, outside any folder.
            $table->foreignId('automation_folder_id')->nullable()->after('channel_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('automation_folder_id');
        });
    }
};
