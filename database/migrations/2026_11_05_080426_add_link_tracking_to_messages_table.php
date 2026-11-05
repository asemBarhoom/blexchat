<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A DM's button goes through /l/{link_token}, which counts the click before sending the
     * customer on: the automation page's clicks and click rate.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('link_token', 32)->nullable()->unique()->after('button_url');
            $table->unsignedInteger('link_clicks')->default(0)->after('link_token');
            $table->timestamp('first_clicked_at')->nullable()->after('link_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['link_token']);
            $table->dropColumn(['link_token', 'link_clicks', 'first_clicked_at']);
        });
    }
};
