<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An editorial reminder the frontend never rendered; it no longer ships in the API.
     */
    public function up(): void
    {
        Schema::table('faq_items', function (Blueprint $table): void {
            $table->dropColumn('claim_unverified');
        });
    }

    public function down(): void
    {
        Schema::table('faq_items', function (Blueprint $table): void {
            $table->boolean('claim_unverified')->default(false);
        });
    }
};
