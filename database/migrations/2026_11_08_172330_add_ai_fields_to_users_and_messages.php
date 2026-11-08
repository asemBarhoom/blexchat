<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blexchat AI: what the owner tells it about the business, which replies it wrote, and the
     * one-line reason it gives when it hands a conversation over.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('business_info')->nullable()->after('business_name');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('by_ai')->default(false)->after('automated');
            $table->string('note', 300)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['by_ai', 'note']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('business_info');
        });
    }
};
