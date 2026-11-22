<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram's public link to the post, so a person can open it from the wizard.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('permalink', 500)->nullable()->after('thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('permalink');
        });
    }
};
